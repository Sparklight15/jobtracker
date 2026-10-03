<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CvCustomization: string
{
    use HasOptions;

    case Generic = 'generic';
    case Tailored = 'tailored';

    public function label(): string
    {
        return match ($this) {
            self::Generic => 'Generik',
            self::Tailored => 'Disesuaikan',
        };
    }
}