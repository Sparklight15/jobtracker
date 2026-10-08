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
    /** Batas jumlah titik pada grafik tren, supaya tetap terbaca. */
    private const WEEKLY_MAX = 26;

    private const MONTHLY_MAX = 12;

    /** Jumlah titik maksimal sparkline kartu. */
    private const SPARK_POINTS = 20;

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
            ],
            'charts' => [
                'stage_duration' => $this->stageDuration($jobs, $minAverage),
                'apply_weekly' => Stat::guard($total, $minBreakdown, fn () => $this->weeklyTrend($context)),
                'apply_monthly' => Stat::guard($total, $minBreakdown, fn () => $this->monthlyTrend($context)),
            ],
        ];
    }

    /**
     * #6 Waktu ke respons pertama: applied_date sampai first_response_date,
     * hanya untuk loker yang sudah direspons.
     */
    private function firstResponse(StatsContext $context, Collection $jobs, int $min): array
    {
        $responded = $jobs->filter(fn (Job $job) => $job->first_response_date !== null)->values();

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

        return Stat::chart('bar', $labels, [['name' => 'Rata-rata hari', 'data' => $data]], ['unit' => 'hari']);
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

    /** #9 Tren apply per minggu (minggu mulai Senin). Minggu pertama bisa parsial. */
    private function weeklyTrend(StatsContext $context): array
    {
        $end = $context->today->startOfWeek(CarbonInterface::MONDAY);
        $start = $this->rangeStart($context)->startOfWeek(CarbonInterface::MONDAY);
        $start = max($start, $end->subWeeks(self::WEEKLY_MAX - 1));

        $counts = $context->jobs()->countBy(
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

        return Stat::chart('line', $labels, [['name' => 'Apply', 'data' => $data]]);
    }

    /** #9 Tren apply per bulan. */
    private function monthlyTrend(StatsContext $context): array
    {
        $end = $context->today->startOfMonth();
        $start = $this->rangeStart($context)->startOfMonth();
        $start = max($start, $end->subMonthsNoOverflow(self::MONTHLY_MAX - 1));

        $counts = $context->jobs()->countBy(
            fn (Job $job) => CarbonImmutable::instance($job->applied_date)->format('Y-m')
        );

        $labels = [];
        $data = [];

        for ($month = $start; $month <= $end; $month = $month->addMonthNoOverflow()) {
            $labels[] = $month->locale('id')->translatedFormat('M Y');
            $data[] = $counts->get($month->format('Y-m'), 0);
        }

        return Stat::chart('line', $labels, [['name' => 'Apply', 'data' => $data]]);
    }

    /** Awal rentang grafik tren: awal periode, atau apply pertama untuk "Semua". */
    private function rangeStart(StatsContext $context): CarbonImmutable
    {
        return $context->period->startDate($context->today)
            ?? CarbonImmutable::instance($context->jobs()->pluck('applied_date')->min())->startOfDay();
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