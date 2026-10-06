<?php

namespace App\Support\Stats\Groups;

use App\Enums\JobStatus;
use App\Models\BenchmarkReference;
use App\Models\Job;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class GroupH implements StatGroup
{
    public function compute(StatsContext $context): array
    {
        $jobs = $context->jobs();
        $minAverage = config('stats.min_sample.average');

        // Load semua benchmark ke memori (di-cache per key)
        $benchmarks = BenchmarkReference::pluck('value', 'metric_key');

        return [
            'cards' => [
                'search_duration_vs_market' => $this->compareSearchDuration($context, $benchmarks->get(BenchmarkReference::JOB_SEARCH_DURATION_MONTHS, 5.5)),
                'market_competition' => Stat::value(
                    $benchmarks->get(BenchmarkReference::APPLICANTS_PER_JOB, 150),
                    ['unit' => 'pelamar/loker', 'note' => 'Rata-rata kompetisi (Benchmark Global)']
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

        return Stat::value($userMonths, [
            'unit' => 'bulan',
            'note' => "{$status} " . abs($delta) . " bulan dari rata-rata pasar ({$marketMonths} bln)",
        ]);
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
}