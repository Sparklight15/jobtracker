<?php

namespace App\Support;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\StatusHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobUpdater
{
    /**
     * Simpan hasil edit dalam satu transaksi:
     * 1. atribut loker (semantik PUT: kolom opsional yang kosong ikut dikosongkan),
     * 2. skill yang kurang disinkronkan (yang masih ada dipertahankan, yang baru ditambah,
     *    yang dihapus dari form ikut dihapus),
     * 3. baris riwayat awal "Applied" dipindahkan kalau tanggal apply berubah,
     * 4. kalau status berubah, JobStatusChanger menambah baris status_history,
     * 5. kolom hasil (alasan penolakan, keputusan dan gaji offer) disesuaikan dengan status saat ini.
     *
     * Status dibandingkan dengan current_status dari baris yang sudah dikunci,
     * bukan dengan apa yang dikirim form.
     *
     * @param  array<string, mixed>  $data  hasil UpdateJobRequest::validated()
     */
    public static function update(Job $job, array $data): Job
    {
        return DB::transaction(function () use ($job, $data) {
            $locked = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            $status = JobStatus::from($data['status']);
            $statusChanged = $status !== $locked->current_status;

            // Status berubah di antara validasi dan penguncian: tanggalnya belum diminta
            if ($statusChanged && empty($data['changed_at'])) {
                throw ValidationException::withMessages([
                    'changed_at' => 'Tanggal status berubah wajib diisi.',
                ]);
            }

            $attributes = [];

            foreach (JobCreator::ATTRIBUTES as $key) {
                $attributes[$key] = $data[$key] ?? null;
            }

            $attributes['has_referral'] = (bool) ($data['has_referral'] ?? false);

            $locked->update($attributes);

            self::syncSkills($locked, $data['skills'] ?? []);
            self::syncInitialEntry($locked, $data['applied_date']);

            if ($statusChanged) {
                $locked = JobStatusChanger::apply($locked, $status, $data['changed_at'], $data);
            }

            // Kalau baris riwayat baru disisipkan di tanggal lampau, status saat ini bisa tidak
            // berubah. Kolom hasil milik status saat ini itu tidak boleh ikut tertimpa.
            if ($locked->current_status === $status) {
                $locked->update([
                    'rejection_reason' => $status === JobStatus::Rejected ? ($data['rejection_reason'] ?? null) : null,
                    'offer_decision' => $status === JobStatus::Offer ? ($data['offer_decision'] ?? null) : null,
                    'salary_offered' => $status === JobStatus::Offer ? ($data['salary_offered'] ?? null) : null,
                ]);
            }

            return $locked->fresh();
        });
    }

    /**
     * Baris riwayat "Applied" yang dibuat saat loker ditambahkan (id terkecil).
     */
    public static function initialAppliedEntry(Job $job): ?StatusHistory
    {
        return StatusHistory::query()
            ->where('job_id', $job->id)
            ->where('status', JobStatus::Applied->value)
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<int, string>  $skills  sudah dirapikan dan bebas duplikat oleh request
     */
    private static function syncSkills(Job $job, array $skills): void
    {
        $wanted = [];

        foreach ($skills as $skill) {
            $wanted[mb_strtolower($skill)] ??= $skill;
        }

        $kept = [];

        foreach ($job->skillGaps()->get() as $gap) {
            $key = mb_strtolower($gap->skill_name);

            if (isset($wanted[$key]) && ! isset($kept[$key])) {
                // Dipertahankan; nama diperbarui kalau hanya huruf besar-kecilnya yang berubah
                if ($gap->skill_name !== $wanted[$key]) {
                    $gap->update(['skill_name' => $wanted[$key]]);
                }

                $kept[$key] = true;
            } else {
                // Dihapus dari form (atau baris ganda lama)
                $gap->delete();
            }
        }

        $new = array_diff_key($wanted, $kept);

        if ($new !== []) {
            $job->skillGaps()->createMany(
                array_map(fn (string $name) => ['skill_name' => $name], array_values($new))
            );
        }
    }

    private static function syncInitialEntry(Job $job, string $appliedDate): void
    {
        $entry = self::initialAppliedEntry($job);

        if ($entry !== null && Carbon::parse($entry->changed_at)->toDateString() !== $appliedDate) {
            $entry->update(['changed_at' => $appliedDate]);
        }
    }
}