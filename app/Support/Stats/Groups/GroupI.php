<?php

namespace App\Support\Stats\Groups;

use App\Enums\Channel;
use App\Enums\CvCustomization;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Support\Stats\Concerns\SparklineBuckets;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class GroupI implements StatGroup
{
    use SparklineBuckets;

    private const WEEKLY_MAX = 12;

    /** Lamaran yang masih berjalan. */
    private const ACTIVE = [JobStatus::Applied, JobStatus::Screening, JobStatus::Interview];

    /** Lamaran yang sudah selesai (hasil akhir). */
    private const FINISHED = [JobStatus::Rejected, JobStatus::Offer, JobStatus::Ghosted];

    public function compute(StatsContext $context): array
    {
        $jobs = $context->jobs();
        $total = $jobs->count();
        $minPrediction = config('stats.min_sample.prediction', 10);
        $minBreakdown = config('stats.min_sample.breakdown', 5);
        $minPercentage = config('stats.min_sample.percentage', 5);

        $finished = $jobs->whereIn('current_status', self::FINISHED);

        return [
            'cards' => [
                'prediction_score' => Stat::guard($total, $minPrediction, fn () => $this->calculatePredictionScore($context, $jobs)),
                'active_jobs' => Stat::guard($total, $minBreakdown, fn () => $this->activeJobs($context, $jobs)),
                'historical_conversion' => Stat::guard($finished->count(), $minPercentage, fn () => $this->historicalConversion($context, $jobs)),
            ],
            'charts' => [
                'funnel_trend' => Stat::guard($total, $minBreakdown, fn () => $this->weeklyFunnelTrend($context)),
            ],
        ];
    }

    private function calculatePredictionScore(StatsContext $context, Collection $jobs): array
    {
        $baseConversionRate = $this->baseConversionRate($jobs);

        $activeJobs = $jobs->whereIn('current_status', self::ACTIVE);

        if ($activeJobs->isEmpty()) {
            return Stat::value(null, ['note' => 'Tidak ada lamaran aktif untuk diprediksi']);
        }

        $totalScore = 0;

        foreach ($activeJobs as $job) {
            $totalScore += $this->jobScore($job, $baseConversionRate);
        }

        $averageScore = round($totalScore / $activeJobs->count());

        return Stat::value($averageScore, array_filter([
            'unit' => '/ 100',
            'note' => "Rata-rata peluang dari {$activeJobs->count()} lamaran aktif",
            'spark' => $this->scoreSpark($context, $activeJobs, $baseConversionRate),
        ], fn ($v) => $v !== null));
    }

    /**
     * Lamaran Aktif: jumlah lamaran yang masih berjalan (Applied, Screening, Interview),
     * dengan rincian per tahap di catatan.
     */
    private function activeJobs(StatsContext $context, Collection $jobs): array
    {
        $active = $jobs->whereIn('current_status', self::ACTIVE);
        $count = $active->count();
        $total = $jobs->count();

        $applied = $active->where('current_status', JobStatus::Applied)->count();
        $screening = $active->where('current_status', JobStatus::Screening)->count();
        $interview = $active->where('current_status', JobStatus::Interview)->count();

        $note = $count === 0
            ? "Tidak ada lamaran aktif dari {$total} loker"
            : "{$interview} interview · {$screening} screening · {$applied} applied";

        return Stat::value($count, array_filter([
            'unit' => 'loker',
            'note' => $note,
            'spark' => $this->activeSpark($context, $active),
        ], fn ($v) => $v !== null));
    }

    /**
     * Conversion Historis: dari lamaran yang sudah selesai (Rejected, Offer, Ghosted),
     * berapa persen yang pernah sampai Interview atau Offer. Angka ini dipakai sebagai
     * dasar rumus Skor Prediksi Keberhasilan.
     */
    private function historicalConversion(StatsContext $context, Collection $jobs): array
    {
        $finished = $jobs->whereIn('current_status', self::FINISHED);
        $reached = $finished->filter(fn (Job $job) => $this->reachedInterviewOrOffer($job))->count();
        $count = $finished->count();

        return Stat::value(round($reached / $count * 100, 1), array_filter([
            'unit' => '%',
            'note' => "{$reached} dari {$count} lamaran selesai pernah sampai Interview atau Offer",
            'spark' => $this->historicalSpark($context, $finished),
        ], fn ($v) => $v !== null));
    }

    /** Dasar conversion (0 sampai 1) dari lamaran yang sudah selesai. */
    private function baseConversionRate(Collection $jobs): float
    {
        $finished = $jobs->whereIn('current_status', self::FINISHED);

        if ($finished->isEmpty()) {
            return 0.0;
        }

        $reached = $finished->filter(fn (Job $job) => $this->reachedInterviewOrOffer($job))->count();

        return $reached / $finished->count();
    }

    private function reachedInterviewOrOffer(Job $job): bool
    {
        return $job->statusHistory->whereIn('status', [JobStatus::Interview, JobStatus::Offer])->isNotEmpty();
    }

    /** Skor peluang satu lamaran aktif. */
    private function jobScore(Job $job, float|int $baseConversionRate): float|int
    {
        $score = 0;

        if ($job->cv_customization === CvCustomization::Tailored) {
            $score += 30;
        }

        if ($job->channel === Channel::Referral) {
            $score += 20;
        } elseif ($job->channel === Channel::JobPortal) {
            $score += 5;
        }

        return $score + ($baseConversionRate * 50);
    }

    /**
     * Bagi lamaran ke "ember" waktu berdasarkan applied_date.
     * Rentang ember selalu dihitung dari semua loker pada periode, supaya
     * sparkline ketiga card sejajar. Null kalau titik kurang dari 2.
     *
     * @return list<list<Job>>|null
     */
    private function bucketJobs(StatsContext $context, Collection $subset): ?array
    {
        $start = $this->sparkStart($context, $context->jobs());

        if ($start === null) {
            return null;
        }

        $layout = $this->bucketLayout($start, $context->today);

        if ($layout === null) {
            return null;
        }

        [$step, $count] = $layout;
        $buckets = array_fill(0, $count, []);

        foreach ($subset as $job) {
            if (! $job->applied_date) {
                continue;
            }

            $buckets[$this->bucketOf($start, $job->applied_date, $step, $count)][] = $job;
        }

        return $buckets;
    }

    /**
     * Sparkline skor prediksi: rata-rata skor lamaran aktif per ember waktu.
     * Ember tanpa lamaran aktif dilewati. Kurang dari 2 titik = null.
     *
     * @return list<float>|null
     */
    private function scoreSpark(StatsContext $context, Collection $activeJobs, float|int $baseConversionRate): ?array
    {
        $buckets = $this->bucketJobs($context, $activeJobs);

        if ($buckets === null) {
            return null;
        }

        $series = [];

        foreach ($buckets as $bucket) {
            if ($bucket === []) {
                continue;
            }

            $sum = 0;

            foreach ($bucket as $job) {
                $sum += $this->jobScore($job, $baseConversionRate);
            }

            $series[] = round($sum / count($bucket));
        }

        return count($series) >= 2 ? $series : null;
    }

    /**
     * Sparkline Lamaran Aktif: jumlah lamaran aktif per ember waktu.
     * Kalau semua nol = null, supaya tidak menampilkan garis datar.
     *
     * @return list<int>|null
     */
    private function activeSpark(StatsContext $context, Collection $active): ?array
    {
        $buckets = $this->bucketJobs($context, $active);

        if ($buckets === null) {
            return null;
        }

        $series = array_map('count', $buckets);

        return max($series) > 0 ? $series : null;
    }

    /**
     * Sparkline Conversion Historis: persen lamaran selesai yang sampai Interview
     * atau Offer per ember waktu. Ember tanpa lamaran selesai dilewati.
     * Kurang dari 2 titik atau semua nol = null.
     *
     * @return list<float>|null
     */
    private function historicalSpark(StatsContext $context, Collection $finished): ?array
    {
        $buckets = $this->bucketJobs($context, $finished);

        if ($buckets === null) {
            return null;
        }

        $series = [];

        foreach ($buckets as $bucket) {
            if ($bucket === []) {
                continue;
            }

            $reached = 0;

            foreach ($bucket as $job) {
                if ($this->reachedInterviewOrOffer($job)) {
                    $reached++;
                }
            }

            $series[] = round($reached / count($bucket) * 100, 1);
        }

        return count($series) >= 2 && max($series) > 0 ? $series : null;
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