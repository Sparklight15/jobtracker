<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Channel: string
{
    use HasOptions;

    case JobPortal = 'job_portal';
    case Linkedin = 'linkedin';
    case Referral = 'referral';
    case ColdApply = 'cold_apply';
    case RecruiterReachOut = 'recruiter_reach_out';
    case CompanyWebsite = 'company_website';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::JobPortal => 'Job Portal',
            self::Linkedin => 'LinkedIn',
            self::Referral => 'Referral',
            self::ColdApply => 'Cold Apply',
            self::RecruiterReachOut => 'Recruiter Reach Out',
            self::CompanyWebsite => 'Company Website',
            self::Other => 'Lainnya',
        };
    }
}