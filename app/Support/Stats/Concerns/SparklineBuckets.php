<?php

namespace App\Support\Stats\Concerns;

use App\Support\Stats\StatsContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pembagian "ember" waktu untuk sparkline kartu angka.
 * Minimal 7 hari per titik, dilebarkan supaya maksimal $maxPoints titik.
 */
trait SparklineBuckets
{
    /** Awal rentang sparkline: awal periode, atau apply pertama untuk "Semua". */
    private function sparkStart(StatsContext $context, Collection $jobs): ?CarbonImmutable
    {
        $start = $context->period->startDate($context->today);

        if ($start !== null) {
            return $start;
        }

        $first = $jobs->pluck('applied_date')->filter()->min();

        return $first ? CarbonImmutable::instance($first)->startOfDay() : null;
    }

    /**
     * @return array{0: int, 1: int}|null [hari per ember, jumlah ember]; null kalau titiknya kurang dari 2
     */
    private function bucketLayout(CarbonImmutable $start, CarbonImmutable $today, int $maxPoints = 20): ?array
    {
        $days = (int) floor(abs($start->diffInDays($today->startOfDay())));
        $step = max(7, (int) ceil(($days + 1) / $maxPoints));
        $count = intdiv($days, $step) + 1;

        return $count >= 2 ? [$step, $count] : null;
    }

    /** Nomor ember (0..count-1) untuk sebuah tanggal. */
    private function bucketOf(CarbonImmutable $start, DateTimeInterface $date, int $step, int $count): int
    {
        $day = CarbonImmutable::instance($date)->startOfDay();
        $index = (int) floor(abs($start->diffInDays($day)) / $step);

        return min($index, $count - 1);
    }
}