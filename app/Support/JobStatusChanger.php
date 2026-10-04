<?php

namespace App\Support;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\DB;

class JobStatusChanger
{
    /**
     * Status yang dihitung sebagai respons pertama dari perusahaan.
     * Applied dan Ghosted tidak masuk: keduanya berarti belum ada kabar.
     */
    private const RESPONSE_STATUSES = ['screening', 'interview', 'offer', 'rejected'];

    /**
     * Baris riwayat yang akan berada tepat sebelum dan sesudah sebuah tanggal.
     * Baris baru selalu diurutkan setelah baris lain di tanggal yang sama (id lebih besar).
     *
     * @return array{prev: ?StatusHistory, next: ?StatusHistory}
     */
    public static function neighbors(Job $job, string $date): array
    {
        $prev = StatusHistory::query()
            ->where('job_id', $job->id)
            ->where('changed_at', '<=', $date)
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->first();

        $next = StatusHistory::query()
            ->where('job_id', $job->id)
            ->where('changed_at', '>', $date)
            ->orderBy('changed_at')
            ->orderBy('id')
            ->first();

        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * Ubah status: tambah baris status_history dan perbarui jobs dalam satu transaksi.
     *
     * @param  array<string, mixed>  $extra  rejection_reason, offer_decision, salary_offered (opsional)
     */
    public static function apply(Job $job, JobStatus $status, string $date, array $extra = []): Job
    {
        return DB::transaction(function () use ($job, $status, $date, $extra) {
            // Kunci baris loker supaya dua permintaan bersamaan tidak saling menimpa
            $locked = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            $entry = $locked->statusHistory()->create([
                'status' => $status,
                'changed_at' => $date,
            ]);

            // Status saat ini = baris dengan tanggal terbaru (bukan sekadar yang terakhir diinput)
            $latest = StatusHistory::query()
                ->where('job_id', $locked->id)
                ->orderByDesc('changed_at')
                ->orderByDesc('id')
                ->first();

            $attributes = ['current_status' => $latest->status];

            // Respons pertama: diisi sekali, tidak menimpa nilai yang sudah ada
            if ($locked->first_response_date === null) {
                $firstResponse = StatusHistory::query()
                    ->where('job_id', $locked->id)
                    ->whereIn('status', self::RESPONSE_STATUSES)
                    ->min('changed_at');

                if ($firstResponse) {
                    $attributes['first_response_date'] = $firstResponse;
                }
            }

            // Data hasil hanya disimpan kalau baris baru ini memang menjadi status saat ini
            if ($latest->is($entry)) {
                if ($status === JobStatus::Rejected && ! empty($extra['rejection_reason'])) {
                    $attributes['rejection_reason'] = $extra['rejection_reason'];
                }

                if ($status === JobStatus::Offer) {
                    if (! empty($extra['offer_decision'])) {
                        $attributes['offer_decision'] = $extra['offer_decision'];
                    }

                    if (isset($extra['salary_offered'])) {
                        $attributes['salary_offered'] = (int) $extra['salary_offered'];
                    }
                }
            }

            $locked->update($attributes);

            return $locked;
        });
    }
}