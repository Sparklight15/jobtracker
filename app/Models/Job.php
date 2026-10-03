<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\CvCustomization;
use App\Enums\IndustrySector;
use App\Enums\JobStatus;
use App\Enums\OfferDecision;
use App\Enums\RejectionReason;
use App\Enums\WorkMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    // user_id sengaja tidak ada di sini: diisi lewat $user->jobs()->create(...)
    protected $fillable = [
        'company_name',
        'position',
        'job_url',
        'current_status',
        'applied_date',
        'first_response_date',
        'channel',
        'has_referral',
        'referrer_name',
        'industry_sector',
        'city',
        'work_mode',
        'salary_min',
        'salary_max',
        'salary_offered',
        'fit_score',
        'cv_customization',
        'skill_match_score',
        'rejection_reason',
        'offer_decision',
        'notes',
    ];

    protected $casts = [
        'current_status' => JobStatus::class,
        'channel' => Channel::class,
        'industry_sector' => IndustrySector::class,
        'work_mode' => WorkMode::class,
        'cv_customization' => CvCustomization::class,
        'rejection_reason' => RejectionReason::class,
        'offer_decision' => OfferDecision::class,
        'applied_date' => 'date',
        'first_response_date' => 'date',
        'has_referral' => 'boolean',
        'salary_min' => 'integer',
        'salary_max' => 'integer',
        'salary_offered' => 'integer',
        'fit_score' => 'integer',
        'skill_match_score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }

    public function skillGaps(): HasMany
    {
        return $this->hasMany(JobSkillGap::class);
    }
}