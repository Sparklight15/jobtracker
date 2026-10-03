<?php

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusHistory extends Model
{
    protected $table = 'status_history';

    // Tabel hanya punya created_at, tanpa updated_at
    const UPDATED_AT = null;

    protected $fillable = ['status', 'changed_at'];

    protected $casts = [
        'status' => JobStatus::class,
        'changed_at' => 'date',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}