<?php

namespace App\Support\Stats\Groups;

use App\Enums\CvCustomization;
use App\Models\Job;
use App\Support\Stats\Concerns\ReachesInterview;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grup F (#18-21): kualitas lamaran.
 *
 * Aturan:
 * - #18, #19, #21 memakai conversion (lihat trait ReachesInterview), dibagi per
 *   kelompok. Kelompok dengan sampel di bawah batas ditampilkan kosong (null).
 *   Loker yang fit_score atau cv_customization-nya kosong tidak ikut kelompok mana pun.
 * - #21 membandingkan has_referral (kolom di loker), bukan channel, jadi loker
 *   yang dapat referral lewat channel apa pun ikut terhitung.
 * - #20 menghitung berapa loker yang kekurangan tiap skill (satu skill dihitung
 *   sekali per loker). Nama skill dinormalkan seperti kota di Grup E, dan
 *   hanya TOP_SKILLS teratas yang ditampilkan.
 */
class GroupF implements StatGroup
{
    use ReachesInterview;

    private const TOP_SKILLS = 10;

    private const FIT_SCORES = [1, 2, 3, 4, 5];

    public function compute(StatsContext $context): array
    {
        $jobs = $context->jobs()->loadMissing('skillGaps');

        $minRate = config('stats.min_sample.percentage');
        $minBreakdown = config('stats.min_sample.breakdown');

        return [
            'cards' => [],
            'charts' => [
                'fit_score_conversion' => $this->rateChart($this->byFitScore($jobs), $minRate, 'Conversion'),
                'cv_conversion' => $this->rateChart($this->byCv($jobs), $minRate, 'Conversion'),
                'skill_gaps' => $this->skillGaps($jobs, $minBreakdown),
                'referral_conversion' => $this->rateChart($this->byReferral($jobs), $minRate, 'Conversion'),
            ],
        ];
    }

    /** @return list<array{label: string, jobs: Collection}> */
    private function byFitScore(Collection $jobs): array
    {
        return array_map(
            fn (int $score) => [
                'label' => (string) $score,
                'jobs' => $jobs->filter(fn (Job $job) => $job->fit_score === $score),
            ],
            self::FIT_SCORES,
        );
    }

    /** @return list<array{label: string, jobs: Collection}> */
    private function byCv(Collection $jobs): array
    {
        return array_map(
            fn (CvCustomization $cv) => [
                'label' => $cv->label(),
                'jobs' => $jobs->filter(fn (Job $job) => $job->cv_customization === $cv),
            ],
            CvCustomization::cases(),
        );
    }

    /** @return list<array{label: string, jobs: Collection}> */
    private function byReferral(Collection $jobs): array
    {
        return [
            ['label' => 'Dengan referral', 'jobs' => $jobs->filter(fn (Job $job) => (bool) $job->has_referral)],
            ['label' => 'Tanpa referral', 'jobs' => $jobs->reject(fn (Job $job) => (bool) $job->has_referral)],
        ];
    }

    /**
     * Grafik batang persentase conversion per kelompok.
     *
     * @param  list<array{label: string, jobs: Collection}>  $groups
     */
    private function rateChart(array $groups, int $min, string $seriesName): array
    {
        $rows = array_map(
            fn (array $group) => [
                'label' => $group['label'],
                'total' => $group['jobs']->count(),
                'converted' => $group['jobs']->filter(fn (Job $job) => $this->reachedInterview($job))->count(),
            ],
            $groups,
        );

        $largest = max(array_column($rows, 'total'));

        if ($largest < $min) {
            return Stat::insufficient($largest, $min);
        }

        return Stat::chart(
            'bar',
            array_column($rows, 'label'),
            [[
                'name' => $seriesName,
                'data' => array_map(
                    fn (array $row) => $row['total'] >= $min
                        ? round($row['converted'] / $row['total'] * 100, 1)
                        : null,
                    $rows,
                ),
            ]],
            [
                'unit' => '%',
                'counts' => array_map(
                    fn (array $row) => ['n' => $row['converted'], 'of' => $row['total']],
                    $rows,
                ),
            ],
        );
    }

    /** #20 Skill yang paling sering kurang: top sepuluh, urut terbanyak (seri: abjad). */
    private function skillGaps(Collection $jobs, int $min): array
    {
        $skills = $jobs->flatMap(
            fn (Job $job) => $job->skillGaps
                ->pluck('skill_name')
                ->map(fn ($name) => $this->cleanSkill($name))
                ->filter()
                ->unique(fn (string $name) => mb_strtolower($name))
                ->values()
        )->values();

        return Stat::guard($skills->count(), $min, function () use ($skills) {
            $rows = [];

            foreach ($skills->groupBy(fn (string $name) => mb_strtolower($name)) as $group) {
                $rows[] = [
                    'label' => (string) $group->countBy()->sortDesc()->keys()->first(),
                    'count' => $group->count(),
                ];
            }

            usort($rows, fn (array $a, array $b) => ($b['count'] <=> $a['count'])
                ?: strcasecmp($a['label'], $b['label']));

            $top = array_slice($rows, 0, self::TOP_SKILLS);

            return Stat::chart(
                'hbar',
                array_column($top, 'label'),
                [['name' => 'Jumlah loker', 'data' => array_column($top, 'count')]],
            );
        });
    }

    /** Rapikan spasi; kosong menjadi null. */
    private function cleanSkill(?string $name): ?string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

        return $clean === '' ? null : $clean;
    }
}