<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BenchmarkTrend: string
{
    use HasOptions;

    case Growing = 'growing';
    case Stable = 'stable';
    case Shrinking = 'shrinking';

    public function label(): string
    {
        return match ($this) {
            self::Growing => 'Tumbuh',
            self::Stable => 'Stabil',
            self::Shrinking => 'Menyusut',
        };
    }
}