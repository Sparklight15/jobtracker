<?php

namespace App\Support\Stats\Concerns;

use App\Models\Job;

/**
 * Tahap funnel terjauh yang pernah dicapai sebuah loker (Applied 0 .. Offer 3).
 *
 * Dibaca dari current_status DAN status_history. Rejected dan Ghosted bukan tahap
 * (stageOrder() = null), jadi dilewati. Dipakai Grup A dan Grup I.
 *
 * Butuh relasi statusHistory sudah dimuat (StatsContext::jobs() sudah melakukannya).
 */
trait FurthestStage
{
    /** Urutan tahap funnel terjauh yang pernah dicapai (minimal 0 = Applied). */
    protected function furthestStage(Job $job): int
    {
        $orders = [$job->current_status->stageOrder()];

        foreach ($job->statusHistory as $entry) {
            $orders[] = $entry->status->stageOrder();
        }

        $orders = array_filter($orders, fn ($order) => $order !== null);

        return $orders === [] ? 0 : max($orders);
    }
}