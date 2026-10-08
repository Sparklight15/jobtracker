<?php

namespace App\Support\Stats\Groups;

use App\Enums\ApplyTargetPeriod;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Support\Stats\Concerns\FurthestStage;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grup A (#1-5): funnel lamaran.
 *
 * Definisi: loker "mencapai" sebuah tahap kalau riwayat (atau status saat ini)
 * memuat tahap itu atau tahap yang lebih jauh di funnel
 * Applied -> Screening -> Interview -> Offer. Jadi loker yang loncat dari
 * Applied langsung ke Interview tetap dihitung lolos Screening.
 * Rejected dan Ghosted bukan tahap, hanya hasil akhir.
 */
class GroupA implements StatGroup
{
    use FurthestStage;

    public function compute(StatsContext $context): array
    {
        $jobs = $context->jobs();
        $total = $jobs->count();

        $minPercentage = config('stats.min_sample.percentage');
        $minBreakdown = config('stats.min_sample.breakdown');

        $funnel = JobStatus::funnel();
        $reached = $this->reachedCounts($jobs);

        $byCurrent = $jobs->countBy(fn (Job $job) => $job->current_status->value);
        $ghosted = $byCurrent->get(JobStatus::Ghosted->value, 0);

        [$target, $targetNote] = $this->targetFor($context, $total);

        // Data sparkline untuk tiga kartu angka (null = sparkline tidak ditampilkan)
        $spark = $this->sparkSeries($context, $jobs);

        return [
            'cards' => [
                // #1 total apply vs target
                'total_apply' => Stat::value($total, array_filter([
                    'unit' => 'loker',
                    'target' => $target,
                    'note' => $targetNote,
                    'spark' => $spark['total'],
                ], fn ($v) => $v !== null)),

                // #4 overall conversion: pernah mencapai Offer / total
                'overall_conversion' => Stat::guard($total, $minPercentage, fn () => Stat::value(
                    $this->percent($reached[JobStatus::Offer->value], $total),
                    array_filter([
                        'unit' => '%',
                        'note' => "{$reached[JobStatus::Offer->value]} dari {$total} loker sampai tahap Offer",
                        'spark' => $spark['conversion'],
                    ], fn ($v) => $v !== null),
                )),

                // #5 rasio ghosting: status saat ini Ghosted / total
                'ghosting_rate' => Stat::guard($total, $minPercentage, fn () => Stat::value(
                    $this->percent($ghosted, $total),
                    array_filter([
                        'unit' => '%',
                        'note' => "{$ghosted} dari {$total} loker tanpa kabar",
                        'spark' => $spark['ghosting'],
                    ], fn ($v) => $v !== null),
                )),
            ],

            'charts' => [
                // #2 jumlah per tahap (pernah mencapai)
                'per_stage' => Stat::guard($total, $minBreakdown, fn () => Stat::chart(
                    'bar',
                    array_map(fn (JobStatus $s) => $s->label(), $funnel),
                    [['name' => 'Pernah mencapai', 'data' => array_values($reached)]],
                )),

                // #3 conversion antar tahap
                'stage_conversion' => Stat::guard($total, $minPercentage, function () use ($funnel, $reached) {
                    $labels = [];
                    $data = [];

                    for ($i = 0; $i < count($funnel) - 1; $i++) {
                        $from = $funnel[$i];
                        $to = $funnel[$i + 1];

                        $labels[] = "{$from->label()} → {$to->label()}";
                        $data[] = $reached[$from->value] > 0
                            ? $this->percent($reached[$to->value], $reached[$from->value])
                            : null;
                    }

                    return Stat::chart('hbar', $labels, [['name' => 'Conversion', 'data' => $data]], ['unit' => '%']);
                }),

                // Pelengkap #2: sebaran status saat ini (termasuk Rejected dan Ghosted)
                'status_now' => Stat::guard($total, $minBreakdown, fn () => Stat::chart(
                    'bar',
                    array_map(fn (JobStatus $s) => $s->label(), JobStatus::cases()),
                    [[
                        'name' => 'Jumlah',
                        'data' => array_map(fn (JobStatus $s) => $byCurrent->get($s->value, 0), JobStatus::cases()),
                    ]],
                )),
            ],
        ];
    }

    /**
     * Jumlah loker yang mencapai tiap tahap funnel, berkunci nilai status.
     *
     * @return array<string, int>
     */
    private function reachedCounts(Collection $jobs): array
    {
        $furthest = $jobs->map(fn (Job $job) => $this->furthestStage($job));

        $reached = [];

        foreach (JobStatus::funnel() as $stage) {
            $reached[$stage->value] = $furthest
                ->filter(fn (int $order) => $order >= $stage->stageOrder())
                ->count();
        }

        return $reached;
    }

    /**
     * Data sparkline per kartu, dibagi ke "ember" waktu berdasarkan applied_date.
     * 30d/90d: satu titik per minggu. Semua: dilebarkan supaya maksimal ~20 titik.
     * Kalau titik kurang dari 2, semuanya null (sparkline tidak ditampilkan).
     *
     * @return array{total: ?array, conversion: ?array, ghosting: ?array}
     */
    private function sparkSeries(StatsContext $context, Collection $jobs): array
    {
        $none = ['total' => null, 'conversion' => null, 'ghosting' => null];
        $today = $context->today->startOfDay();
        $start = $context->period->startDate($today);

        if ($start === null) {
            $first = $jobs->pluck('applied_date')->filter()->min();

            if (! $first) {
                return $none;
            }

            $start = CarbonImmutable::instance($first)->startOfDay();
        }

        $days = (int) floor(abs($start->diffInDays($today)));
        $step = max(7, (int) ceil(($days + 1) / 20));
        $count = intdiv($days, $step) + 1;

        if ($count < 2) {
            return $none;
        }

        $buckets = array_fill(0, $count, []);

        foreach ($jobs as $job) {
            if (! $job->applied_date) {
                continue;
            }

            $applied = CarbonImmutable::instance($job->applied_date)->startOfDay();
            $index = (int) floor(abs($start->diffInDays($applied)) / $step);
            $buckets[min($index, $count - 1)][] = $job;
        }

        $offerOrder = JobStatus::Offer->stageOrder();
        $total = $conversion = $ghosting = [];

        foreach ($buckets as $bucket) {
            $n = count($bucket);
            $offers = 0;
            $ghosts = 0;

            foreach ($bucket as $job) {
                if ($this->furthestStage($job) >= $offerOrder) {
                    $offers++;
                }
                if ($job->current_status === JobStatus::Ghosted) {
                    $ghosts++;
                }
            }

            $total[] = $n;
            $conversion[] = $this->percent($offers, $n);
            $ghosting[] = $this->percent($ghosts, $n);
        }

        return compact('total', 'conversion', 'ghosting');
    }

    /**
     * Target apply yang dikalikan panjang periode.
     * Per minggu: target x hari / 7. Per bulan: target x hari / 30.
     *
     * @return array{0: ?int, 1: ?string} [target periode ini, catatan untuk kartu]
     */
    private function targetFor(StatsContext $context, int $total): array
    {
        $user = $context->user;

        if (! $user->apply_target || ! $user->apply_target_period) {
            return [null, 'Target belum diatur.'];
        }

        $days = $this->periodDays($context);

        if ($days === null) {
            return [null, null];
        }

        $perDay = $user->apply_target / ($user->apply_target_period === ApplyTargetPeriod::Week ? 7 : 30);
        $target = (int) round($perDay * $days);

        if ($target < 1) {
            return [null, null];
        }

        $progress = (int) round($total / $target * 100);

        return [$target, "Target periode ini: {$target} loker ({$progress}%)"];
    }

    /**
     * Panjang periode dalam hari (inklusif). Untuk "Semua": dari yang lebih awal
     * antara awal pencarian kerja dan apply pertama, sampai hari ini.
     */
    private function periodDays(StatsContext $context): ?int
    {
        $start = $context->period->startDate($context->today);

        if ($start === null) {
            $candidates = [];

            if ($context->user->job_search_started_at) {
                $candidates[] = CarbonImmutable::instance($context->user->job_search_started_at)->startOfDay();
            }

            $firstApply = $context->jobs()->pluck('applied_date')->min();

            if ($firstApply) {
                $candidates[] = CarbonImmutable::instance($firstApply)->startOfDay();
            }

            if ($candidates === []) {
                return null;
            }

            $start = min($candidates);
        }

        return max(1, (int) abs($start->diffInDays($context->today)) + 1);
    }

    private function percent(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}