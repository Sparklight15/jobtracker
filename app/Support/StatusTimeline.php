<?php

namespace App\Support;

use App\Enums\JobStatus;
use App\Models\StatusHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class StatusTimeline
{
    /**
     * Susun riwayat status menjadi timeline lengkap dengan lama tiap tahap (hari).
     *
     * @param  iterable<StatusHistory>  $history
     * @return Collection<int, array{status: JobStatus, date: CarbonInterface, days: ?int, isCurrent: bool, isOngoing: bool}>
     */
    public static function build(iterable $history, ?CarbonInterface $today = null): Collection
    {
        $today = ($today ?? now())->copy()->startOfDay();

        // Urut tanggal, lalu id kalau tanggalnya sama
        $rows = collect($history)
            ->sortBy(fn (StatusHistory $row) => sprintf('%s-%010d', $row->changed_at->format('Y-m-d'), $row->id))
            ->values();

        $last = $rows->count() - 1;

        return $rows->map(function (StatusHistory $row, int $i) use ($rows, $last, $today) {
            $isCurrent = $i === $last;
            $isOngoing = $row->status->isOngoing();

            if (! $isCurrent) {
                $end = $rows[$i + 1]->changed_at;
            } elseif ($isOngoing) {
                $end = $today;
            } else {
                $end = null;
            }

            $days = $end
                ? (int) $row->changed_at->copy()->startOfDay()->diffInDays($end->copy()->startOfDay(), true)
                : null;

            return [
                'status' => $row->status,
                'date' => $row->changed_at,
                'days' => $days,
                'isCurrent' => $isCurrent,
                'isOngoing' => $isOngoing,
            ];
        });
    }
}