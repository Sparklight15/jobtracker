<?php

namespace App\Support\Stats;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Filter periode global Beranda (8.5).
 * Basis cohort = tanggal apply (jobs.applied_date), bukan tanggal event.
 */
enum StatsPeriod: string
{
    case Days30 = '30d';
    case Days90 = '90d';
    case All = 'all';

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value)
            ?? self::from(config('stats.default_period', '90d'));
    }

    public function label(): string
    {
        return match ($this) {
            self::Days30 => '30 hari',
            self::Days90 => '90 hari',
            self::All => 'Semua',
        };
    }

    /**
     * Tanggal apply paling awal yang masuk cohort (inklusif), null = tanpa batas.
     * "30 hari" = hari ini plus 29 hari sebelumnya.
     */
    public function startDate(?DateTimeInterface $today = null): ?CarbonImmutable
    {
        $today = $today ? CarbonImmutable::instance($today) : CarbonImmutable::today();
        $today = $today->startOfDay();

        return match ($this) {
            self::Days30 => $today->subDays(29),
            self::Days90 => $today->subDays(89),
            self::All => null,
        };
    }

    /** Untuk render tombol filter di view. */
    public static function options(): array
    {
        return array_map(
            fn (self $p) => ['value' => $p->value, 'label' => $p->label()],
            self::cases()
        );
    }
}
