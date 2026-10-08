<?php

namespace App\Support\Stats\Groups;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grup B (#6-9): waktu dan tren.
 *
 * Semua selisih waktu dalam hari penuh, karena status_history.changed_at hanya
 * menyimpan tanggal (perpindahan di hari yang sama = 0 hari).
 */
class GroupB implements StatGroup
{
    /** Batas jumlah titik pada tampilan harian, supaya tetap terbaca. */
    private const DAILY_MAX = 30;

    /** Rentang harian minimal, supaya garis tidak jadi satu titik saat data baru sebentar. */
    private const DAILY_MIN = 7;

    private const WEEKLY_MAX = 26;

    private const MONTHLY_MAX = 12;

    /** Jumlah titik maksimal sparkline kartu. */
    private const SPARK_POINTS = 20;

    /** Jumlah minggu (kolom) kalender aktivitas. */
    private const CALENDAR_MAX_WEEKS = 26;

    private const CALENDAR_MIN_WEEKS = 8;

    /**
     * Urutan status dalam satu hari (juga urutan bagian warna di kotak).
     * Hasil terjauh di funnel diutamakan, Applied terakhir karena paling sering.
     */
    private const CALENDAR_PRIORITY = [
        JobStatus::Offer,
        JobStatus::Interview,
        JobStatus::Screening,
        JobStatus::Rejected,
        JobStatus::Ghosted,
        JobStatus::Applied,
    ];

    /** Tahap yang punya lama durasi. Offer, Rejected, dan Ghosted adalah akhir. */
    private const TRACKED_STAGES = [JobStatus::Applied, JobStatus::Screening, JobStatus::Interview];

    public function compute(StatsContext $context): array
    {
        $jobs = $context->jobs();
        $total = $jobs->count();

        $minAverage = config('stats.min_sample.average');
        $minBreakdown = config('stats.min_sample.breakdown');

        return [
            'cards' => [
                'first_response_days' => $this->firstResponse($context, $jobs, $minAverage),
                'search_duration' => $this->searchDuration($context),
                'apply_pace' => $this->applyPace($context, $jobs, $minBreakdown),
            ],
            // Key di sini HARUS sama dengan atribut name="..." pada <x-chart-panel>
            // di resources/views/stats/group-b.blade.php.
            'charts' => [
                'stage_duration' => $this->stageDuration($jobs, $minAverage),
                'apply_trend' => Stat::guard($total, $minBreakdown, fn () => $this->applyTrend($context)),
                'activity_calendar' => Stat::guard($total, $minBreakdown, fn () => $this->activityCalendar($context)),
            ],
        ];
    }

    /**
     * Rata-rata apply per minggu dalam periode yang dipilih.
     * Panjang periode dihitung dari awal periode (atau apply pertama untuk "Semua")
     * sampai hari ini, minimal 1 minggu supaya periode pendek tidak membengkak.
     */
    private function applyPace(StatsContext $context, Collection $jobs, int $min): array
    {
        return Stat::guard($jobs->count(), $min, function () use ($context, $jobs) {
            $start = $this->rangeStart($context);
            $days = $this->daysBetween($start, $context->today) + 1;
            $count = $jobs->count();
            $perWeek = round($count / max($days / 7, 1), 1);

            return Stat::value($perWeek, array_filter([
                'unit' => 'loker/minggu',
                'note' => "{$count} loker dalam {$days} hari",
                'spark' => $this->applySpark($context, $start, $jobs),
            ], fn ($v) => $v !== null));
        });
    }

    /** Sparkline apply per minggu: jumlah apply per "ember" waktu. Kurang dari 2 titik = null. */
    private function applySpark(StatsContext $context, CarbonImmutable $start, Collection $jobs): ?array
    {
        $layout = $this->bucketLayout($start, $context->today);

        if ($layout === null) {
            return null;
        }

        [$step, $count] = $layout;
        $series = array_fill(0, $count, 0);

        foreach ($jobs as $job) {
            if (! $job->applied_date) {
                continue;
            }

            $series[$this->bucketOf($start, $job->applied_date, $step, $count)]++;
        }

        return $series;
    }

    /**
     * #6 Waktu ke respons pertama: applied_date sampai first_response_date,
     * hanya untuk loker yang sudah direspons.
     */
    private function firstResponse(StatsContext $context, Collection $jobs, int $min): array
    {
        $responded = $jobs
            ->filter(fn (Job $job) => $job->first_response_date !== null && $job->applied_date !== null)
            ->values();

        $days = $responded
            ->map(fn (Job $job) => $this->daysBetween($job->applied_date, $job->first_response_date))
            ->values();

        return Stat::guard($days->count(), $min, fn () => Stat::value(
            round($days->avg(), 1),
            array_filter([
                'unit' => 'hari',
                'note' => 'Median '.$this->number($days->median()).' hari · dari '.$days->count().' loker yang direspons',
                'spark' => $this->firstResponseSpark($context, $jobs, $responded),
            ], fn ($v) => $v !== null),
        ));
    }

    /**
     * Sparkline #6: rata-rata hari ke respons per "ember" waktu (berdasarkan applied_date).
     * Ember tanpa loker yang direspons dilewati. Kurang dari 2 titik = null.
     */
    private function firstResponseSpark(StatsContext $context, Collection $jobs, Collection $responded): ?array
    {
        $start = $context->period->startDate($context->today);

        if ($start === null) {
            $first = $jobs->pluck('applied_date')->filter()->min();

            if (! $first) {
                return null;
            }

            $start = CarbonImmutable::instance($first)->startOfDay();
        }

        $layout = $this->bucketLayout($start, $context->today);

        if ($layout === null) {
            return null;
        }

        [$step, $count] = $layout;
        $sums = array_fill(0, $count, 0);
        $counts = array_fill(0, $count, 0);

        foreach ($responded as $job) {
            $index = $this->bucketOf($start, $job->applied_date, $step, $count);
            $sums[$index] += $this->daysBetween($job->applied_date, $job->first_response_date);
            $counts[$index]++;
        }

        $series = [];

        for ($i = 0; $i < $count; $i++) {
            if ($counts[$i] > 0) {
                $series[] = round($sums[$i] / $counts[$i], 1);
            }
        }

        return count($series) >= 2 ? $series : null;
    }

    /**
     * #7 Waktu per tahap: rata-rata lama di Applied, Screening, dan Interview.
     * Digambar sebagai bar horizontal (tipe 'hbar'): tiga tahap berurutan dari atas
     * ke bawah, batang mengisi lebar kartu.
     *
     * Aturan:
     * - hanya tahap yang sudah selesai (ada status sesudahnya), tahap yang masih
     *   berjalan tidak dihitung supaya rata-rata tidak bias,
     * - perpindahan ke Ghosted dilewati, karena tanggalnya keputusan pengguna
     *   dan bukan keputusan perusahaan,
     * - tahap dengan sampel kurang dari batas ditampilkan kosong.
     */
    private function stageDuration(Collection $jobs, int $min): array
    {
        $durations = [];

        foreach (self::TRACKED_STAGES as $stage) {
            $durations[$stage->value] = [];
        }

        foreach ($jobs as $job) {
            $entries = $job->statusHistory->values();

            for ($i = 0; $i < $entries->count() - 1; $i++) {
                $current = $entries[$i];
                $next = $entries[$i + 1];

                if (! in_array($current->status, self::TRACKED_STAGES, true)) {
                    continue;
                }

                if ($next->status === JobStatus::Ghosted) {
                    continue;
                }

                $durations[$current->status->value][] = $this->daysBetween($current->changed_at, $next->changed_at);
            }
        }

        $largest = max(array_map('count', $durations));

        if ($largest < $min) {
            return Stat::insufficient($largest, $min);
        }

        $labels = [];
        $data = [];

        foreach (self::TRACKED_STAGES as $stage) {
            $values = $durations[$stage->value];

            $labels[] = $stage->label();
            $data[] = count($values) >= $min ? round(array_sum($values) / count($values), 1) : null;
        }

        return Stat::chart('hbar', $labels, [['name' => 'Rata-rata hari', 'data' => $data]], ['unit' => 'hari']);
    }

    /**
     * #8 Durasi job search: dari yang lebih awal antara awal pencarian dan apply
     * pertama (semua loker, tidak terpengaruh filter periode) sampai hari ini.
     */
    private function searchDuration(StatsContext $context): array
    {
        $candidates = [];

        if ($context->user->job_search_started_at) {
            $candidates[] = CarbonImmutable::instance($context->user->job_search_started_at)->startOfDay();
        }

        $firstApply = Job::query()->where('user_id', $context->user->id)->min('applied_date');

        if ($firstApply) {
            $candidates[] = CarbonImmutable::parse($firstApply)->startOfDay();
        }

        if ($candidates === []) {
            return Stat::insufficient(0, 1);
        }

        $start = min($candidates);

        return Stat::value($this->daysBetween($start, $context->today) + 1, array_filter([
            'unit' => 'hari',
            'note' => 'Sejak '.$start->locale('id')->translatedFormat('j F Y'),
            'spark' => $this->searchSpark($context, $start),
        ], fn ($v) => $v !== null));
    }

    /**
     * Sparkline #8: jumlah apply per "ember" waktu sejak awal pencarian
     * (semua loker, tidak terpengaruh filter periode). Kurang dari 2 titik = null.
     */
    private function searchSpark(StatsContext $context, CarbonImmutable $start): ?array
    {
        $layout = $this->bucketLayout($start, $context->today);

        if ($layout === null) {
            return null;
        }

        [$step, $count] = $layout;
        $series = array_fill(0, $count, 0);

        $dates = Job::query()
            ->where('user_id', $context->user->id)
            ->pluck('applied_date');

        foreach ($dates as $date) {
            if (! $date) {
                continue;
            }

            $series[$this->bucketOf($start, $date, $step, $count)]++;
        }

        return $series;
    }

    /**
     * #9 Tren apply dengan tiga rentang (hari, minggu, bulan) dalam satu grafik.
     * Ketiga seri dikirim sekaligus di 'views'; tombol di panel (lazy-load.js)
     * memilih salah satunya tanpa request baru. Label dan seri tingkat atas
     * adalah tampilan bawaan (minggu), jadi payload tetap bisa digambar sebagai
     * grafik garis biasa kalau 'views' diabaikan.
     */
    private function applyTrend(StatsContext $context): array
    {
        $views = [
            'day' => $this->trendView('Hari', 'Apply per hari', $this->dailySeries($context)),
            'week' => $this->trendView('Minggu', 'Apply per minggu', $this->weeklySeries($context)),
            'month' => $this->trendView('Bulan', 'Apply per bulan', $this->monthlySeries($context)),
        ];

        return Stat::chart(
            'line',
            $views['week']['labels'],
            $views['week']['series'],
            ['views' => $views, 'default_view' => 'week'],
        );
    }

    /**
     * @param  string  $label  teks tombol
     * @param  string  $name  nama seri (muncul di tooltip)
     * @param  array{0: list<string>, 1: list<int>}  $series  [label sumbu, data]
     * @return array{label: string, labels: list<string>, series: list<array{name: string, data: list<int>}>}
     */
    private function trendView(string $label, string $name, array $series): array
    {
        [$labels, $data] = $series;

        return ['label' => $label, 'labels' => $labels, 'series' => [['name' => $name, 'data' => $data]]];
    }

    /**
     * Apply per hari: maksimal DAILY_MAX hari terakhir (minimal DAILY_MIN hari),
     * dipotong oleh awal periode yang dipilih.
     *
     * @return array{0: list<string>, 1: list<int>}
     */
    private function dailySeries(StatsContext $context): array
    {
        $end = $context->today->startOfDay();
        $start = max($this->rangeStart($context), $end->subDays(self::DAILY_MAX - 1));
        $start = min($start, $end->subDays(self::DAILY_MIN - 1));

        $counts = $context->jobs()
            ->filter(fn (Job $job) => $job->applied_date !== null)
            ->countBy(fn (Job $job) => CarbonImmutable::instance($job->applied_date)->toDateString());

        $labels = [];
        $data = [];

        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $labels[] = $day->locale('id')->translatedFormat('j M');
            $data[] = $counts->get($day->toDateString(), 0);
        }

        return [$labels, $data];
    }

    /**
     * Apply per minggu (minggu mulai Senin), maksimal WEEKLY_MAX minggu terakhir.
     * Minggu pertama bisa parsial.
     *
     * @return array{0: list<string>, 1: list<int>}
     */
    private function weeklySeries(StatsContext $context): array
    {
        $end = $context->today->startOfWeek(CarbonInterface::MONDAY);
        $start = $this->rangeStart($context)->startOfWeek(CarbonInterface::MONDAY);
        $start = max($start, $end->subWeeks(self::WEEKLY_MAX - 1));

        $counts = $context->jobs()
            ->filter(fn (Job $job) => $job->applied_date !== null)
            ->countBy(
                fn (Job $job) => CarbonImmutable::instance($job->applied_date)
                    ->startOfWeek(CarbonInterface::MONDAY)
                    ->toDateString()
            );

        $labels = [];
        $data = [];

        for ($week = $start; $week <= $end; $week = $week->addWeek()) {
            $labels[] = $week->locale('id')->translatedFormat('j M');
            $data[] = $counts->get($week->toDateString(), 0);
        }

        return [$labels, $data];
    }

    /**
     * Apply per bulan, maksimal MONTHLY_MAX bulan terakhir.
     *
     * @return array{0: list<string>, 1: list<int>}
     */
    private function monthlySeries(StatsContext $context): array
    {
        $end = $context->today->startOfMonth();
        $start = $this->rangeStart($context)->startOfMonth();
        $start = max($start, $end->subMonthsNoOverflow(self::MONTHLY_MAX - 1));

        $counts = $context->jobs()
            ->filter(fn (Job $job) => $job->applied_date !== null)
            ->countBy(
                fn (Job $job) => CarbonImmutable::instance($job->applied_date)->format('Y-m')
            );

        $labels = [];
        $data = [];

        for ($month = $start; $month <= $end; $month = $month->addMonthNoOverflow()) {
            $labels[] = $month->locale('id')->translatedFormat('M Y');
            $data[] = $counts->get($month->format('Y-m'), 0);
        }

        return [$labels, $data];
    }

    /**
     * Kalender aktivitas: satu kotak = satu hari, kolom = minggu (mulai Senin).
     * Setiap hari membawa daftar kejadian per status dari status_history
     * (plus applied_date untuk loker yang riwayat Applied-nya belum ada).
     *
     * Berbeda dengan grafik lain, kejadian diambil dari SEMUA loker milik user,
     * bukan hanya loker yang apply-nya jatuh di periode. Kalau tidak, wawancara
     * hari ini untuk loker yang di-apply 3 bulan lalu tidak akan muncul di filter 30 hari.
     * Filter periode hanya menentukan berapa minggu yang ditampilkan.
     */
    private function activityCalendar(StatsContext $context): array
    {
        $today = $context->today->startOfDay();
        $lastWeek = $today->startOfWeek(CarbonInterface::MONDAY);

        $first = $this->rangeStart($context)->startOfWeek(CarbonInterface::MONDAY);
        $first = max($first, $lastWeek->subWeeks(self::CALENDAR_MAX_WEEKS - 1));
        $first = min($first, $lastWeek->subWeeks(self::CALENDAR_MIN_WEEKS - 1));

        $from = $first->toDateString();
        $to = $today->toDateString();

        $jobs = Job::query()
            ->where('user_id', $context->user->id)
            ->where(fn ($query) => $query
                ->where('applied_date', '>=', $from)
                ->orWhereHas('statusHistory', fn ($history) => $history->where('changed_at', '>=', $from)))
            ->with(['statusHistory' => fn ($query) => $query->where('changed_at', '>=', $from)])
            ->get();

        // [tanggal][status][id loker] => nama; kunci id loker mencegah hitungan ganda
        $events = [];

        $add = function (DateTimeInterface $date, JobStatus $status, int $jobId, string $name) use (&$events, $from, $to): void {
            $key = CarbonImmutable::instance($date)->toDateString();

            if ($key < $from || $key > $to) {
                return;
            }

            $events[$key][$status->value][$jobId] = $name;
        };

        foreach ($jobs as $job) {
            $name = $job->position ? "{$job->company_name} · {$job->position}" : (string) $job->company_name;

            foreach ($job->statusHistory as $entry) {
                $add($entry->changed_at, $entry->status, $job->id, $name);
            }

            $hasAppliedEntry = $job->statusHistory->contains(fn ($entry) => $entry->status === JobStatus::Applied);

            if (! $hasAppliedEntry && $job->applied_date) {
                $add($job->applied_date, JobStatus::Applied, $job->id, $name);
            }
        }

        $weeks = [];
        $previousMonth = null;

        for ($monday = $first; $monday <= $lastWeek; $monday = $monday->addWeek()) {
            $days = [];

            for ($i = 0; $i < 7; $i++) {
                $day = $monday->addDays($i);
                $key = $day->toDateString();

                $days[] = [
                    'date' => $key,
                    'label' => $day->locale('id')->translatedFormat('l, j F Y'),
                    'future' => $day > $today,
                    'events' => $this->calendarEvents($events[$key] ?? []),
                ];
            }

            $weeks[] = [
                // Nama bulan hanya di minggu pertama bulan itu
                'month' => $monday->month !== $previousMonth ? $monday->locale('id')->translatedFormat('M') : null,
                'days' => $days,
            ];

            $previousMonth = $monday->month;
        }

        // Dua label bulan di kolom bersebelahan akan bertabrakan: pertahankan yang kedua
        if (isset($weeks[1]) && $weeks[1]['month'] !== null) {
            $weeks[0]['month'] = null;
        }

        return Stat::chart('calendar', [], [], [
            'weeks' => $weeks,
            'legend' => array_map(
                fn (JobStatus $status) => ['status' => $status->value, 'label' => $status->label()],
                JobStatus::cases(),
            ),
        ]);
    }

    /**
     * @param  array<string, array<int, string>>  $byStatus  [status => [id loker => nama]]
     * @return list<array{status: string, label: string, items: list<string>}>
     */
    private function calendarEvents(array $byStatus): array
    {
        $events = [];

        foreach (self::CALENDAR_PRIORITY as $status) {
            $items = $byStatus[$status->value] ?? [];

            if ($items === []) {
                continue;
            }

            $events[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'items' => array_values($items),
            ];
        }

        return $events;
    }

    /**
     * Awal rentang grafik tren: awal periode, atau apply pertama untuk "Semua".
     * Kalau tidak ada loker ber-applied_date, jatuh ke hari ini (bukan error).
     */
    private function rangeStart(StatsContext $context): CarbonImmutable
    {
        $start = $context->period->startDate($context->today);

        if ($start !== null) {
            return $start;
        }

        $first = $context->jobs()->pluck('applied_date')->filter()->min();

        return $first
            ? CarbonImmutable::instance($first)->startOfDay()
            : $context->today->startOfDay();
    }

    /**
     * Ukuran "ember" sparkline: minimal 7 hari per titik, dilebarkan supaya
     * maksimal SPARK_POINTS titik. Null kalau titiknya kurang dari 2.
     *
     * @return array{0: int, 1: int}|null [hari per ember, jumlah ember]
     */
    private function bucketLayout(CarbonImmutable $start, CarbonImmutable $today): ?array
    {
        $days = (int) floor(abs($start->diffInDays($today->startOfDay())));
        $step = max(7, (int) ceil(($days + 1) / self::SPARK_POINTS));
        $count = intdiv($days, $step) + 1;

        return $count >= 2 ? [$step, $count] : null;
    }

    /** Nomor ember (0..count-1) untuk sebuah tanggal. */
    private function bucketOf(CarbonImmutable $start, DateTimeInterface $date, int $step, int $count): int
    {
        $day = CarbonImmutable::instance($date)->startOfDay();
        $index = (int) floor(abs($start->diffInDays($day)) / $step);

        return min($index, $count - 1);
    }

    private function daysBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) abs(
            CarbonImmutable::instance($from)->startOfDay()->diffInDays(CarbonImmutable::instance($to)->startOfDay())
        );
    }

    /** Angka berformat Indonesia, satu desimal, tanpa ",0" di belakang. */
    private function number(int|float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }
}