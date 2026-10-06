<?php

namespace App\Support\Stats\Groups;

use App\Enums\Channel;
use App\Enums\CvCustomization;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class GroupI implements StatGroup
{
    private const WEEKLY_MAX = 12;

    public function compute(StatsContext $context): array
    {
        $jobs = $context->jobs();
        $total = $jobs->count();
        $minPrediction = config('stats.min_sample.prediction', 10);
        $minBreakdown = config('stats.min_sample.breakdown', 5);

        return [
            'cards' => [
                'prediction_score' => Stat::guard($total, $minPrediction, fn () => $this->calculatePredictionScore($jobs)),
            ],
            'charts' => [
                'funnel_trend' => Stat::guard($total, $minBreakdown, fn () => $this->weeklyFunnelTrend($context)),
            ],
        ];
    }

    private function calculatePredictionScore(Collection $jobs): array
    {
        $historicalJobs = $jobs->whereIn('current_status', [JobStatus::Rejected, JobStatus::Offer, JobStatus::Ghosted]);
        
        $baseConversionRate = 0;
        if ($historicalJobs->count() > 0) {
            $reachedInterviewOrOffer = $historicalJobs->filter(fn (Job $job) => 
                $job->statusHistory->whereIn('status', [JobStatus::Interview, JobStatus::Offer])->isNotEmpty()
            )->count();
            
            $baseConversionRate = $reachedInterviewOrOffer / $historicalJobs->count();
        }

        $activeJobs = $jobs->whereIn('current_status', [JobStatus::Applied, JobStatus::Screening, JobStatus::Interview]);
        
        if ($activeJobs->isEmpty()) {
            return Stat::value(null, ['note' => 'Tidak ada lamaran aktif untuk diprediksi']);
        }

        $totalScore = 0;

        foreach ($activeJobs as $job) {
            $score = 0;

            if ($job->cv_customization === CvCustomization::Tailored) {
                $score += 30;
            }

            if ($job->channel === Channel::Referral) {
                $score += 20;
            } elseif ($job->channel === Channel::JobPortal) {
                $score += 5;
            }

            $score += ($baseConversionRate * 50);
            $totalScore += $score;
        }

        $averageScore = round($totalScore / $activeJobs->count());

        return Stat::value($averageScore, [
            'unit' => '/ 100',
            'note' => "Rata-rata peluang dari {$activeJobs->count()} lamaran aktif"
        ]);
    }

    private function weeklyFunnelTrend(StatsContext $context): array
    {
        $end = $context->today->startOfWeek(CarbonInterface::MONDAY);
        
        // PERBAIKAN: Mencegah error perbandingan saat filter "Semua" aktif (startDate null)
        $startPeriod = $context->period->startDate($context->today);
        $defaultStart = $end->copy()->subWeeks(self::WEEKLY_MAX - 1);
        
        $start = $startPeriod 
            ? max($startPeriod->startOfWeek(CarbonInterface::MONDAY), $defaultStart) 
            : $defaultStart;

        $labels = [];
        $series = [
            'Applied' => [],
            'Interview' => [],
            'Offer' => [],
            'Rejected' => [],
        ];

        for ($week = $start; $week <= $end; $week = $week->addWeek()) {
            $labels[] = $week->locale('id')->translatedFormat('j M');
            $weekEnd = $week->endOfWeek();

            $applied = 0;
            $interview = 0;
            $offer = 0;
            $rejected = 0;

            foreach ($context->jobs() as $job) {
                $statusAtWeek = $job->statusHistory
                    ->where('changed_at', '<=', $weekEnd->toDateString())
                    ->last();

                if ($statusAtWeek) {
                    match ($statusAtWeek->status) {
                        JobStatus::Applied, JobStatus::Screening => $applied++,
                        JobStatus::Interview => $interview++,
                        JobStatus::Offer => $offer++,
                        JobStatus::Rejected, JobStatus::Ghosted => $rejected++,
                        default => null,
                    };
                }
            }

            $series['Applied'][] = $applied;
            $series['Interview'][] = $interview;
            $series['Offer'][] = $offer;
            $series['Rejected'][] = $rejected;
        }

        $chartSeries = [];
        foreach ($series as $name => $data) {
            $chartSeries[] = ['name' => $name, 'data' => $data];
        }

        return Stat::chart('bar', $labels, $chartSeries, ['stacked' => true]); 
    }
}