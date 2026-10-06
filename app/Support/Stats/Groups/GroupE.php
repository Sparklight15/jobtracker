<?php

namespace App\Support\Stats\Groups;

use App\Enums\WorkMode;
use App\Models\Job;
use App\Support\Stats\Concerns\ReachesInterview;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grup E (#16-17): lokasi dan mode kerja.
 *
 * Aturan:
 * - Kota dinormalkan (spasi ganda dan huruf besar-kecil diabaikan), jadi "jakarta"
 *   dan "Jakarta " dihitung satu. Label yang tampil adalah ejaan yang paling sering dipakai.
 * - Distribusi kota menampilkan TOP_CITIES teratas, sisanya digabung jadi "Lainnya".
 * - Conversion = pernah mencapai Interview atau Offer (sama dengan Grup C).
 * - Mode kerja dengan sampel di bawah batas ditampilkan kosong (null), bukan 0%.
 */
class GroupE implements StatGroup
{
    use ReachesInterview;

    private const TOP_CITIES = 5;

    public function compute(StatsContext $context): array
    {
        $jobs = $context->jobs();

        return [
            'cards' => [],
            'charts' => [
                'city_distribution' => $this->cityDistribution($jobs, config('stats.min_sample.breakdown')),
                'work_mode_conversion' => $this->workModeConversion($jobs, config('stats.min_sample.percentage')),
            ],
        ];
    }

    /** #16 Distribusi kota: top lima, sisanya "Lainnya". Loker tanpa kota tidak dihitung. */
    private function cityDistribution(Collection $jobs, int $min): array
    {
        $cities = $jobs
            ->map(fn (Job $job) => $this->cleanCity($job->city))
            ->filter()
            ->values();

        return Stat::guard($cities->count(), $min, function () use ($cities) {
            $rows = $cities
                ->groupBy(fn (string $city) => mb_strtolower($city))
                ->map(fn ($group) => [
                    'label' => (string) $group->countBy()->sortDesc()->keys()->first(),
                    'count' => $group->count(),
                ])
                ->values()
                ->all();

            usort($rows, fn (array $a, array $b) => ($b['count'] <=> $a['count'])
                ?: strcasecmp($a['label'], $b['label']));

            $top = array_slice($rows, 0, self::TOP_CITIES);
            $rest = array_slice($rows, self::TOP_CITIES);

            if ($rest !== []) {
                $top[] = ['label' => 'Lainnya', 'count' => array_sum(array_column($rest, 'count'))];
            }

            return Stat::chart(
                'hbar',
                array_column($top, 'label'),
                [['name' => 'Jumlah loker', 'data' => array_column($top, 'count')]],
            );
        });
    }

    /** #17 Conversion per mode kerja, urutan tetap: Remote, Hybrid, Onsite. */
    private function workModeConversion(Collection $jobs, int $min): array
    {
        $rows = [];

        foreach (WorkMode::cases() as $mode) {
            $group = $jobs->filter(fn (Job $job) => $job->work_mode === $mode);

            $rows[] = [
                'label' => $mode->label(),
                'total' => $group->count(),
                'converted' => $group->filter(fn (Job $job) => $this->reachedInterview($job))->count(),
            ];
        }

        $largest = max(array_column($rows, 'total'));

        if ($largest < $min) {
            return Stat::insufficient($largest, $min);
        }

        return Stat::chart(
            'bar',
            array_column($rows, 'label'),
            [[
                'name' => 'Conversion',
                'data' => array_map(
                    fn (array $row) => $row['total'] >= $min
                        ? round($row['converted'] / $row['total'] * 100, 1)
                        : null,
                    $rows,
                ),
            ]],
            [
                'unit' => '%',
                // Jumlah mentah per mode kerja, untuk tooltip "3 dari 12".
                'counts' => array_map(
                    fn (array $row) => ['n' => $row['converted'], 'of' => $row['total']],
                    $rows,
                ),
            ],
        );
    }

    /** Rapikan spasi; kosong menjadi null. */
    private function cleanCity(?string $city): ?string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', (string) $city));

        return $clean === '' ? null : $clean;
    }
}