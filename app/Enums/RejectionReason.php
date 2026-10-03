<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RejectionReason: string
{
    use HasOptions;

    case SkillGap = 'skill_gap';
    case Overqualified = 'overqualified';
    case SalaryMismatch = 'salary_mismatch';
    case NoResponse = 'no_response';
    case CultureFit = 'culture_fit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SkillGap => 'Skill kurang',
            self::Overqualified => 'Overqualified',
            self::SalaryMismatch => 'Gaji tidak sesuai',
            self::NoResponse => 'Tidak ada respons',
            self::CultureFit => 'Culture fit',
            self::Other => 'Lainnya',
        };
    }
}