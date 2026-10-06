<?php

namespace App\Support\Stats;

use App\Models\Job;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Satu request = satu konteks. Cohort dimuat sekali lalu dipakai semua grup.
 * $today bisa disuntik supaya uji akurasi (8.21) deterministik.
 */
final class StatsContext
{
    private ?Collection $jobs = null;

    public function __construct(
        public readonly User $user,
        public readonly StatsPeriod $period,
        public readonly CarbonImmutable $today,
    ) {}

    /** Query dasar cohort: selalu terscope user dan periode. */
    public function query(): Builder
    {
        $query = Job::query()->where('user_id', $this->user->id);

        if ($from = $this->period->startDate($this->today)) {
            $query->where('applied_date', '>=', $from->toDateString());
        }

        return $query;
    }

    /** Cohort lengkap dengan riwayat status terurut (changed_at, lalu id). */
    public function jobs(): Collection
    {
        return $this->jobs ??= $this->query()
            ->with(['statusHistory' => fn ($q) => $q->orderBy('changed_at')->orderBy('id')])
            ->get();
    }
}
