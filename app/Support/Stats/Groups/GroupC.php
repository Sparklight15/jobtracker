<?php

namespace App\Support\Stats\Groups;

use App\Models\Job;
use App\Support\Stats\Concerns\ReachesInterview;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grup C (#10-12): analisis per channel.
 *
 * #11 (conversion) dan #12 (response rate) digabung jadi satu grafik garis dengan dua series.
 *
 * Definisi (samakan dengan yang tampil di UI):
 * - Response rate = loker dengan first_response_date terisi / total loker channel itu
 *   (konsisten dengan #6 di Grup B).
 * - Conversion    = loker yang pernah mencapai Interview atau Offer / total loker channel itu.
 *   "Pernah" dicek dari status_history DAN current_status, jadi loker yang
 *   sekarang Rejected tapi dulu sampai Interview tetap dihitung.
 * - Channel dengan sampel di bawah batas ditampilkan kosong (null), bukan 0%.
 */
class GroupC implements StatGroup
{
    use ReachesInterview;

    /** Kunci untuk loker yang channel-nya kosong. */
    private const NO_CHANNEL = '_none';

    public function compute(StatsContext $context): array
    {
        $jobs = $context->jobs();
        $total = $jobs->count();

        $minBreakdown = config('stats.min_sample.breakdown');
        $minRate = config('stats.min_sample.percentage');

        return [
            'cards' => [],
            'charts' => [
                'channel_distribution' => Stat::guard($total, $minBreakdown, fn () => $this->distribution($jobs)),
                'channel_rates' => $this->rates($jobs, $minRate),
            ],
        ];
    }

    /** #10 Distribusi loker per channel (donat), urut dari terbanyak. */
    private function distribution(Collection $jobs): array
    {
        $rows = $this->rows($jobs);

        return Stat::chart(
            'bar',
            array_column($rows, 'label'),
            [['name' => 'Jumlah loker', 'data' => array_column($rows, 'total')]],
            ['variant' => 'donut'], // dibaca lazy-load.js untuk memakai donut.js
        );
    }

    /** #11 dan #12: conversion dan response rate per channel dalam satu grafik garis. */
    private function rates(Collection $jobs, int $min): array
    {
        $rows = $this->rows($jobs);

        $largest = $rows === [] ? 0 : max(array_column($rows, 'total'));

        if ($largest < $min) {
            return Stat::insufficient($largest, $min);
        }

        // Persentase per channel; null (garis terputus) kalau sampel channel itu di bawah batas.
        $percent = fn (string $metric) => array_map(
            fn (array $row) => $row['total'] >= $min
                ? round($row[$metric] / $row['total'] * 100, 1)
                : null,
            $rows,
        );

        // Jumlah mentah per channel, supaya tooltip bisa menulis "3 dari 12".
        $counts = fn (string $metric) => array_map(
            fn (array $row) => ['n' => $row[$metric], 'of' => $row['total']],
            $rows,
        );

        return Stat::chart(
            'line',
            array_column($rows, 'label'),
            [
                ['name' => 'Conversion', 'data' => $percent('converted')],
                ['name' => 'Response rate', 'data' => $percent('responded')],
            ],
            [
                'unit' => '%',
                'variant' => 'glow', // dibaca chart-theme.js untuk memakai gaya glow-line.js
                // Sejajar dengan urutan series di atas.
                'series_counts' => [$counts('converted'), $counts('responded')],
            ],
        );
    }

    /**
     * Ringkasan per channel, urut total terbanyak.
     *
     * @return list<array{label: string, total: int, responded: int, converted: int}>
     */
    private function rows(Collection $jobs): array
    {
        $rows = $jobs
            ->groupBy(fn (Job $job) => $job->channel?->value ?? self::NO_CHANNEL)
            ->map(fn (Collection $group) => [
                'label' => $group->first()->channel?->label() ?? 'Tanpa channel',
                'total' => $group->count(),
                'responded' => $group->filter(fn (Job $job) => $job->first_response_date !== null)->count(),
                'converted' => $group->filter(fn (Job $job) => $this->reachedInterview($job))->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        return $rows;
    }
}