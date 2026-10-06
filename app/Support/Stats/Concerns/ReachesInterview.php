<?php

namespace App\Support\Stats\Concerns;

use App\Enums\JobStatus;
use App\Models\Job;

/**
 * Definisi "conversion" yang dipakai semua grup (C, D, E, F):
 * loker pernah mencapai Interview atau Offer.
 *
 * "Pernah" dicek dari current_status DAN status_history, jadi loker yang sekarang
 * Rejected tapi dulu sampai Interview tetap dihitung. Ubah definisinya di sini
 * supaya semua grup ikut berubah.
 *
 * Butuh relasi statusHistory sudah dimuat (StatsContext::jobs() sudah melakukannya).
 */
trait ReachesInterview
{
    protected function reachedInterview(Job $job): bool
    {
        $converted = [JobStatus::Interview, JobStatus::Offer];

        if (in_array($job->current_status, $converted, true)) {
            return true;
        }

        return $job->statusHistory->contains(
            fn ($entry) => in_array($entry->status, $converted, true)
        );
    }
}