<?php

namespace Database\Seeders;

use App\Enums\ApplyTargetPeriod;
use App\Enums\JobStatus;
use App\Models\User;
use App\Models\Job;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AkunSendiriSeeder extends Seeder
{
    private const EMAIL = 'findingsparklight@gmail.com';

    /** Jumlah loker per status akhir (total 50). */
    private const DISTRIBUTION = [
        'applied' => 10,
        'screening' => 8,
        'interview' => 7,
        'offer' => 4,
        'rejected' => 13,
        'ghosted' => 8,
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('AkunSendiriSeeder dilewati di environment production.');

            return;
        }

        $user = User::where('email', self::EMAIL)->first();

        if (! $user) {
            $this->command->error('Akun '.self::EMAIL.' belum terdaftar. Daftar dulu lewat halaman register.');

            return;
        }

        DB::transaction(function () use ($user) {
            // Target apply dan awal pencarian hanya diisi kalau masih kosong
            $user->fill([
                'job_search_started_at' => $user->job_search_started_at ?? today()->subDays(120),
                'apply_target' => $user->apply_target ?? 10,
                'apply_target_period' => $user->apply_target_period ?? ApplyTargetPeriod::Week,
            ])->save();

            foreach (self::DISTRIBUTION as $status => $count) {
                Job::factory()
                    ->count($count)
                    ->inStatus(JobStatus::from($status))
                    ->for($user)
                    ->create();
            }
        });

        $this->command->info('50 loker dummy ditambahkan ke '.self::EMAIL.'.');
    }
}