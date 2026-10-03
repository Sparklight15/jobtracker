<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ApplyTargetPeriod: string
{
    use HasOptions;

    case Week = 'week';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Week => 'Per minggu',
            self::Month => 'Per bulan',
        };
    }
}