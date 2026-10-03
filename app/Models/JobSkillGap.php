<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSkillGap extends Model
{
    // Tabel tidak punya created_at dan updated_at
    public $timestamps = false;

    protected $fillable = ['skill_name'];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}