<?php

namespace App\Support;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class JobCreator
{
    /**
     * Atribut loker yang boleh diisi dari form tambah loker.
     * user_id sengaja tidak ada: selalu diambil dari pengguna yang login.
     */
    public const ATTRIBUTES = [
        'company_name',
        'position',
        'job_url',
        'applied_date',
        'channel',
        'has_referral',
        'referrer_name',
        'industry_sector',
        'city',
        'work_mode',
        'salary_min',
        'salary_max',
        'fit_score',
        'cv_customization',
        'skill_match_score',
        'notes',
    ];

    /**
     * Simpan loker baru dalam satu transaksi:
     * 1. loker (status awal Applied) dan baris riwayat Applied pada tanggal apply,
     * 2. skill yang kurang,
     * 3. kalau status yang dipilih bukan Applied, ubah status lewat JobStatusChanger
     *    supaya riwayat, current_status, dan first_response_date konsisten.
     *
     * @param  array<string, mixed>  $data  hasil StoreJobRequest::validated()
     */
    public static function create(User $user, array $data): Job
    {
        return DB::transaction(function () use ($user, $data) {
            $status = JobStatus::from($data['status']);

            $job = $user->jobs()->create(
                Arr::only($data, self::ATTRIBUTES) + ['current_status' => JobStatus::Applied]
            );

            $job->statusHistory()->create([
                'status' => JobStatus::Applied,
                'changed_at' => $data['applied_date'],
            ]);

            $skills = $data['skills'] ?? [];

            if ($skills !== []) {
                $job->skillGaps()->createMany(
                    array_map(fn (string $skill) => ['skill_name' => $skill], $skills)
                );
            }

            if ($status !== JobStatus::Applied) {
                JobStatusChanger::apply($job, $status, $data['changed_at'], $data);
            }

            return $job->fresh();
        });
    }
}