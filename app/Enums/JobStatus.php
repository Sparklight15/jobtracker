<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JobStatus: string
{
    use HasOptions;

    case Applied = 'applied';
    case Screening = 'screening';
    case Interview = 'interview';
    case Offer = 'offer';
    case Rejected = 'rejected';
    case Ghosted = 'ghosted';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::Screening => 'Screening',
            self::Interview => 'Interview',
            self::Offer => 'Offer',
            self::Rejected => 'Rejected',
            self::Ghosted => 'Ghosted',
        };
    }

    /**
 * Status yang masih berjalan: lama tahap terakhir dihitung sampai hari ini.
 * Offer, Rejected, dan Ghosted dianggap final.
 */
public function isOngoing(): bool
{
    return match ($this) {
        self::Applied, self::Screening, self::Interview => true,
        self::Offer, self::Rejected, self::Ghosted => false,
    };
}
}