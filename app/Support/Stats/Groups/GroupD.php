<?php

namespace App\Support\Stats\Groups;

use App\Models\Job;
use App\Support\Stats\Concerns\ReachesInterview;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grup D (#13-15): analisis per sektor industri.
 *
 * Aturan:
 * - Label sektor memakai nilai enum (teks Indonesia). Sektor dikirim sebagai
 *   array berurutan (labels + data), bukan objek berkunci, jadi karakter seperti
 *   "/" pada "Retail/FMCG" tidak jadi masalah.
 * - Loker tanpa sektor tidak dihitung.
 * - Conversion = pernah mencapai Interview atau Offer (sama dengan Grup C dan E).
 * - Gaji = salary_offered (gaji yang benar-benar ditawarkan), jadi hanya loker
 *   yang sampai Offer dan terisi gajinya yang masuk rata-rata.
 * - Sektor dengan sampel di bawah batas ditampilkan kosong (null), bukan 0.
 */
class GroupD implements StatGroup
{
    use ReachesInterview;

    public function compute(StatsContext $context): array
    {
        $rows = $this->rows($context->jobs());

        return [
            'cards' => [],
            'charts' => [
                'sector_distribution' => $this->distribution($rows, config('stats.min_sample.breakdown')),
                'sector_conversion' => $this->conversion($rows, config('stats.min_sample.percentage')),
                'sector_salary' => $this->salary($rows, config('stats.min_sample.average')),
            ],
        ];
    }

    /** #13 Distribusi loker per sektor, urut terbanyak. */
    private function distribution(array $rows, int $min): array
    {
        $total = array_sum(array_column($rows, 'total'));

        return Stat::guard($total, $min, fn () => Stat::chart(
            'hbar',
            array_column($rows, 'label'),
            [['name' => 'Jumlah loker', 'data' => array_column($rows, 'total')]],
        ));
    }

    /** #14 Conversion per sektor. */
    private function conversion(array $rows, int $min): array
    {
        $largest = $rows === [] ? 0 : max(array_column($rows, 'total'));

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
                'counts' => array_map(
                    fn (array $row) => ['n' => $row['converted'], 'of' => $row['total']],
                    $rows,
                ),
            ],
        );
    }

    /**
     * #15 Rata-rata gaji ditawarkan per sektor (Rupiah, dibulatkan).
     * Hanya sektor yang punya minimal satu gaji ditawarkan, urut jumlah sampel terbanyak.
     */
    private function salary(array $rows, int $min): array
    {
        $rows = array_values(array_filter($rows, fn (array $row) => $row['salaries'] !== []));

        usort($rows, fn (array $a, array $b) => (count($b['salaries']) <=> count($a['salaries']))
            ?: strcasecmp($a['label'], $b['label']));

        $largest = $rows === [] ? 0 : max(array_map(fn (array $row) => count($row['salaries']), $rows));

        if ($largest < $min) {
            return Stat::insufficient($largest, $min);
        }

        return Stat::chart(
            'bar',
            array_column($rows, 'label'),
            [[
                'name' => 'Rata-rata gaji ditawarkan',
                'data' => array_map(
                    fn (array $row) => count($row['salaries']) >= $min
                        ? (int) round(array_sum($row['salaries']) / count($row['salaries']))
                        : null,
                    $rows,
                ),
            ]],
            [
                'unit' => 'Rp',
                // Jumlah loker yang gajinya masuk hitungan, untuk tooltip "dari 3 offer".
                'counts' => array_map(fn (array $row) => ['n' => count($row['salaries'])], $rows),
            ],
        );
    }

    /**
     * Ringkasan per sektor, urut total terbanyak (seri: abjad).
     *
     * @return list<array{label: string, total: int, converted: int, salaries: list<int>}>
     */
    private function rows(Collection $jobs): array
    {
        $grouped = $jobs
            ->filter(fn (Job $job) => $job->industry_sector !== null)
            ->groupBy(fn (Job $job) => $job->industry_sector->value);

        $rows = [];

        foreach ($grouped as $label => $group) {
            $rows[] = [
                'label' => (string) $label,
                'total' => $group->count(),
                'converted' => $group->filter(fn (Job $job) => $this->reachedInterview($job))->count(),
                'salaries' => $group->whereNotNull('salary_offered')->pluck('salary_offered')->values()->all(),
            ];
        }

        usort($rows, fn (array $a, array $b) => ($b['total'] <=> $a['total'])
            ?: strcasecmp($a['label'], $b['label']));

        return $rows;
    }
}