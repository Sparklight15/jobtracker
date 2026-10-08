<?php

namespace App\Support\Stats\Groups;

use App\Enums\JobStatus;
use App\Models\BenchmarkReference;
use App\Models\Job;
use App\Support\Stats\Concerns\SparklineBuckets;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class GroupH implements StatGroup
{
    use SparklineBuckets;

    public function compute(StatsContext $context): array
    {
        $jobs = $context->jobs();
        $minAverage = config('stats.min_sample.average');
        $minPercentage = config('stats.min_sample.percentage');

        // Load semua benchmark ke memori (di-cache per key)
        $benchmarks = BenchmarkReference::pluck('value', 'metric_key');
        $applicantsPerJob = (float) $benchmarks->get(BenchmarkReference::APPLICANTS_PER_JOB, 150);

        return [
            'cards' => [
                'search_duration_vs_market' => $this->compareSearchDuration($context, $benchmarks->get(BenchmarkReference::JOB_SEARCH_DURATION_MONTHS, 5.5)),
                'market_competition' => Stat::value(
                    $benchmarks->get(BenchmarkReference::APPLICANTS_PER_JOB, 150),
                    array_filter([
                        'unit' => 'pelamar/loker',
                        'note' => 'Rata-rata kompetisi (Benchmark Global)',
                        'spark' => $this->competitionSpark($context, $jobs),
                    ], fn ($v) => $v !== null)
                ),
                'offer_chance_vs_market' => Stat::guard(
                    $jobs->count(),
                    $minPercentage,
                    fn () => $this->compareOfferChance($context, $jobs, $applicantsPerJob)
                ),
            ],
            'charts' => [
                'time_to_hire_vs_market' => $this->compareTimeToHire($jobs, $minAverage, $benchmarks->get('time_to_hire_days', 43)),
            ],
        ];
    }

    /**
     * Membandingkan durasi pencarian user dengan benchmark pasar.
     */
    private function compareSearchDuration(StatsContext $context, float $marketMonths): array
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
        $userDays = abs($start->diffInDays($context->today->startOfDay())) + 1;
        $userMonths = round($userDays / 30.44, 1);

        $delta = round($userMonths - $marketMonths, 1);
        $status = $delta <= 0 ? 'Lebih cepat' : 'Lebih lambat';

        // Sparkline: jumlah apply per ember sejak awal pencarian (semua loker, tidak terpengaruh filter periode)
        $dates = Job::query()->where('user_id', $context->user->id)->pluck('applied_date');

        return Stat::value($userMonths, array_filter([
            'unit' => 'bulan',
            'note' => "{$status} " . abs($delta) . " bulan dari rata-rata pasar ({$marketMonths} bln)",
            'spark' => $this->activitySpark($start, $context, $dates),
        ], fn ($v) => $v !== null));
    }

    /**
     * Peluang Offer: persen loker yang pernah sampai Offer, dibandingkan dengan
     * peluang pasar (1 / jumlah pelamar per loker).
     */
    private function compareOfferChance(StatsContext $context, Collection $jobs, float $applicantsPerJob): array
    {
        $total = $jobs->count();
        $offers = $jobs->filter(fn (Job $job) => $this->reachedOffer($job))->count();

        $userRate = round($offers / $total * 100, 1);
        $marketRate = $applicantsPerJob > 0 ? round(100 / $applicantsPerJob, 2) : 0.0;
        $marketText = $this->decimal($marketRate);

        if ($offers === 0) {
            $note = "Belum ada offer dari {$total} loker · pasar ±{$marketText}%";
        } elseif ($marketRate > 0) {
            $ratio = $this->decimal(round($userRate / $marketRate, 1));
            $note = "{$ratio}x peluang pasar (±{$marketText}%) · {$offers} offer dari {$total} loker";
        } else {
            $note = "{$offers} offer dari {$total} loker";
        }

        return Stat::value($userRate, array_filter([
            'unit' => '%',
            'note' => $note,
            'spark' => $this->offerSpark($context, $jobs),
        ], fn ($v) => $v !== null));
    }

    /**
     * Sparkline peluang offer: persen loker yang sampai Offer per ember waktu
     * (berdasarkan applied_date). Kalau semua nol atau titik kurang dari 2 = null,
     * supaya tidak menampilkan garis datar.
     *
     * @return list<float>|null
     */
    private function offerSpark(StatsContext $context, Collection $jobs): ?array
    {
        $start = $this->sparkStart($context, $jobs);

        if ($start === null) {
            return null;
        }

        $layout = $this->bucketLayout($start, $context->today);

        if ($layout === null) {
            return null;
        }

        [$step, $count] = $layout;
        $totals = array_fill(0, $count, 0);
        $offers = array_fill(0, $count, 0);

        foreach ($jobs as $job) {
            if (! $job->applied_date) {
                continue;
            }

            $index = $this->bucketOf($start, $job->applied_date, $step, $count);
            $totals[$index]++;

            if ($this->reachedOffer($job)) {
                $offers[$index]++;
            }
        }

        $series = [];

        for ($i = 0; $i < $count; $i++) {
            if ($totals[$i] > 0) {
                $series[] = round($offers[$i] / $totals[$i] * 100, 1);
            }
        }

        return count($series) >= 2 && max($series) > 0 ? $series : null;
    }

    /** Loker pernah sampai Offer (dari riwayat atau status sekarang). */
    private function reachedOffer(Job $job): bool
    {
        return $job->current_status === JobStatus::Offer
            || $job->statusHistory->contains('status', JobStatus::Offer);
    }

    /**
     * Membandingkan waktu tempuh dari Applied -> Offer antara User vs Pasar.
     */
    private function compareTimeToHire(Collection $jobs, int $min, float $marketDays): array
    {
        // Hanya loker yang sudah mencapai tahap Offer
        $offeredJobs = $jobs->filter(function (Job $job) {
            return $job->statusHistory->contains('status', JobStatus::Offer);
        });

        return Stat::guard($offeredJobs->count(), $min, function () use ($offeredJobs, $marketDays) {
            $userDurations = $offeredJobs->map(function (Job $job) {
                $appliedAt = CarbonImmutable::instance($job->applied_date)->startOfDay();
                $offerEntry = $job->statusHistory->firstWhere('status', JobStatus::Offer);
                $offeredAt = CarbonImmutable::instance($offerEntry->changed_at)->startOfDay();

                return abs($appliedAt->diffInDays($offeredAt));
            })->values();

            $userAverage = round($userDurations->avg(), 1);

            return Stat::chart('hbar', ['Waktu ke Offering (Hari)'], [
                ['name' => 'Anda', 'data' => [$userAverage]],
                ['name' => 'Pasar', 'data' => [$marketDays]],
            ], ['unit' => 'hari']);
        });
    }

    /** Sparkline "Estimasi Pesaing": aktivitas apply dalam periode yang dipilih. */
    private function competitionSpark(StatsContext $context, Collection $jobs): ?array
    {
        $start = $this->sparkStart($context, $jobs);

        return $start === null
            ? null
            : $this->activitySpark($start, $context, $jobs->pluck('applied_date'));
    }

    /**
     * Jumlah apply per ember waktu mulai dari $start. Kurang dari 2 titik = null.
     *
     * @param  iterable<mixed>  $dates  daftar applied_date
     * @return list<int>|null
     */
    private function activitySpark(CarbonImmutable $start, StatsContext $context, iterable $dates): ?array
    {
        $layout = $this->bucketLayout($start, $context->today);

        if ($layout === null) {
            return null;
        }

        [$step, $count] = $layout;
        $series = array_fill(0, $count, 0);

        foreach ($dates as $date) {
            if (! $date) {
                continue;
            }

            $series[$this->bucketOf($start, $date, $step, $count)]++;
        }

        return $series;
    }

    /** Angka berformat Indonesia (koma desimal), tanpa nol di belakang. */
    private function decimal(int|float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}