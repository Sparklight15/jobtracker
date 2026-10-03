<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum WorkMode: string
{
    use HasOptions;

    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case Onsite = 'onsite';

    public function label(): string
    {
        return match ($this) {
            self::Remote => 'Remote',
            self::Hybrid => 'Hybrid',
            self::Onsite => 'Onsite',
        };
    }
}