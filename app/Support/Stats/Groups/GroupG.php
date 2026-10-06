<?php

namespace App\Support\Stats\Groups;

use App\Enums\JobStatus;
use App\Enums\OfferDecision;
use App\Models\Job;
use App\Support\Stats\Contracts\StatGroup;
use App\Support\Stats\Stat;
use App\Support\Stats\StatsContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grup G (#22-24): hasil akhir.
 *
 * Aturan:
 * - #22 dan #23 hanya menghitung loker yang status sekarang Rejected.
 * - #22: loker Rejected tanpa alasan masuk sebagai "Tidak diisi", supaya total
 *   sama dengan jumlah Rejected. "Tidak diisi" selalu di urutan terakhir.
 * - #23: tahap penolakan = status terakhir sebelum Rejected di status_history.
 *   Perpindahan ke Ghosted dilewati (tanggalnya keputusan pengguna, sama seperti
 *   di Grup B), jadi Interview -> Ghosted -> Rejected dihitung "Interview".
 *   Kasus yang tidak bisa dipastikan (tanpa riwayat, atau sebelumnya Offer)
 *   masuk "Lainnya", yang hanya tampil kalau ada isinya.
 * - #24: hanya loker yang status sekarang Offer. Keputusan kosong dianggap Menunggu.
 */
class GroupG implements StatGroup
{
    private const STAGES = [JobStatus::Applied, JobStatus::Screening, JobStatus::Interview];

    private const UNKNOWN_STAGE = 'Lainnya';

    private const NO_REASON = 'Tidak diisi';

    public function compute(StatsContext $context): array
    {
        $jobs = $context->jobs();
        $min = config('stats.min_sample.breakdown');

        $rejected = $jobs->filter(fn (Job $job) => $job->current_status === JobStatus::Rejected)->values();
        $offers = $jobs->filter(fn (Job $job) => $job->current_status === JobStatus::Offer)->values();

        return [
            'cards' => [],
            'charts' => [
                'rejection_reasons' => Stat::guard($rejected->count(), $min, fn () => $this->reasons($rejected)),
                'rejection_stage' => Stat::guard($rejected->count(), $min, fn () => $this->stages($rejected)),
                'offer_decision' => Stat::guard($offers->count(), $min, fn () => $this->decisions($offers)),
            ],
        ];
    }

    /** #22 Alasan rejection, urut terbanyak, "Tidak diisi" terakhir. */
    private function reasons(Collection $rejected): array
    {
        $counts = [];

        foreach ($rejected as $job) {
            $label = $job->rejection_reason?->label() ?? self::NO_REASON;
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        $rows = [];

        foreach ($counts as $label => $count) {
            $rows[] = ['label' => (string) $label, 'count' => $count];
        }

        usort($rows, function (array $a, array $b) {
            $aNone = $a['label'] === self::NO_REASON;
            $bNone = $b['label'] === self::NO_REASON;

            return ($aNone <=> $bNone)
                ?: ($b['count'] <=> $a['count'])
                ?: strcasecmp($a['label'], $b['label']);
        });

        return Stat::chart(
            'hbar',
            array_column($rows, 'label'),
            [['name' => 'Jumlah loker', 'data' => array_column($rows, 'count')]],
        );
    }

    /** #23 Di tahap mana loker ditolak: Applied, Screening, Interview. */
    private function stages(Collection $rejected): array
    {
        $counts = [];

        foreach (self::STAGES as $stage) {
            $counts[$stage->label()] = 0;
        }

        $counts[self::UNKNOWN_STAGE] = 0;

        foreach ($rejected as $job) {
            $counts[$this->rejectedFrom($job)]++;
        }

        if ($counts[self::UNKNOWN_STAGE] === 0) {
            unset($counts[self::UNKNOWN_STAGE]);
        }

        return Stat::chart(
            'bar',
            array_keys($counts),
            [['name' => 'Jumlah loker', 'data' => array_values($counts)]],
        );
    }

    /** #24 Offer diterima vs ditolak vs menunggu. */
    private function decisions(Collection $offers): array
    {
        $counts = [];

        foreach (OfferDecision::cases() as $decision) {
            $counts[$decision->value] = 0;
        }

        foreach ($offers as $job) {
            $counts[($job->offer_decision ?? OfferDecision::Pending)->value]++;
        }

        return Stat::chart(
            'doughnut',
            array_map(fn (OfferDecision $decision) => $decision->label(), OfferDecision::cases()),
            [['name' => 'Jumlah offer', 'data' => array_values($counts)]],
        );
    }

    /** Label tahap sebelum Rejected, atau "Lainnya" jika tidak bisa dipastikan. */
    private function rejectedFrom(Job $job): string
    {
        $entries = $job->statusHistory->values();
        $lastRejected = null;

        foreach ($entries as $index => $entry) {
            if ($entry->status === JobStatus::Rejected) {
                $lastRejected = $index;
            }
        }

        if ($lastRejected === null) {
            return self::UNKNOWN_STAGE;
        }

        for ($i = $lastRejected - 1; $i >= 0; $i--) {
            $status = $entries[$i]->status;

            if ($status === JobStatus::Ghosted || $status === JobStatus::Rejected) {
                continue;
            }

            return in_array($status, self::STAGES, true) ? $status->label() : self::UNKNOWN_STAGE;
        }

        return self::UNKNOWN_STAGE;
    }
}