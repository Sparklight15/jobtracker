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

    /**
     * Urutan tahap di funnel: Applied 0, Screening 1, Interview 2, Offer 3.
     * Rejected dan Ghosted adalah hasil akhir negatif, bukan tahap, jadi null.
     */
    public function stageOrder(): ?int
    {
        return match ($this) {
            self::Applied => 0,
            self::Screening => 1,
            self::Interview => 2,
            self::Offer => 3,
            self::Rejected, self::Ghosted => null,
        };
    }

    /** Tahap funnel berurutan (dipakai statistik Beranda). */
    public static function funnel(): array
    {
        return [self::Applied, self::Screening, self::Interview, self::Offer];
    }
}