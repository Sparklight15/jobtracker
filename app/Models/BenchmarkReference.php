<?php

namespace App\Models;

use App\Enums\BenchmarkTrend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BenchmarkReference extends Model
{
    use HasFactory;

    public const APPLICANTS_PER_JOB = 'applicants_per_job';
    public const JOB_SEARCH_DURATION_MONTHS = 'job_search_duration_months';
    public const SCHOOL_TO_WORK_MONTHS = 'school_to_work_months';
    public const SECTOR_TREND = 'sector_trend';

    protected $table = 'benchmark_reference';

    protected $fillable = [
        'metric_key',
        'sector',
        'value',
        'trend',
        'source',
        'source_year',
        'notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'trend' => BenchmarkTrend::class,
        'source_year' => 'integer',
    ];
}