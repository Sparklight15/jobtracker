<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OfferDecision: string
{
    use HasOptions;

    case Accepted = 'accepted';
    case Declined = 'declined';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Diterima',
            self::Declined => 'Ditolak',
            self::Pending => 'Menunggu',
        };
    }
}