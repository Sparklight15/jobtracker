<?php

namespace App\Support\Stats;

use Closure;

/**
 * Pembuat blok kartu/grafik sesuai kontrak JSON (8.1).
 * Semua grup wajib memakai helper ini supaya bentuknya seragam.
 */
final class Stat
{
    public const CHART_TYPES = ['bar', 'hbar', 'line', 'doughnut', 'scatter'];

    /** Kartu dengan data cukup. $extra: unit, target, delta, hint, dst. */
    public static function value(int|float|string|null $value, array $extra = []): array
    {
        return ['status' => 'ok', 'value' => $value] + $extra;
    }

    /** Keadaan "data belum cukup" (dipakai kartu maupun grafik). */
    public static function insufficient(int $current, int $minRequired): array
    {
        return [
            'status' => 'insufficient',
            'current' => $current,
            'min_required' => $minRequired,
        ];
    }

    /**
     * Grafik. $series = [['name' => 'Jumlah', 'data' => [..]], ...]
     * Untuk scatter: labels = [], data = [['x' => 1, 'y' => 2], ...]
     */
    public static function chart(string $type, array $labels, array $series, array $extra = []): array
    {
        if (! in_array($type, self::CHART_TYPES, true)) {
            throw new \InvalidArgumentException("Tipe grafik tidak dikenal: {$type}");
        }

        return ['status' => 'ok', 'type' => $type, 'labels' => $labels, 'series' => $series] + $extra;
    }

    /** Jalankan $build hanya jika sampel cukup, selain itu kembalikan insufficient. */
    public static function guard(int $sample, int $minRequired, Closure $build): array
    {
        return $sample >= $minRequired
            ? $build()
            : self::insufficient($sample, $minRequired);
    }
}
