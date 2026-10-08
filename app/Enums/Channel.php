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
    case SocialMedia = 'social_media';
    case CommunityGroup = 'community_group';
    case JobFair = 'job_fair';
    case OfflineMedia = 'offline_media';
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
            self::SocialMedia => 'Media Sosial',
            self::CommunityGroup => 'Grup Komunitas',
            self::JobFair => 'Job Fair',
            self::OfflineMedia => 'Koran, Flyer & Spanduk',
            self::Other => 'Lainnya',
        };
    }
}