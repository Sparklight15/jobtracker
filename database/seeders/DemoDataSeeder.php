<?php

namespace Database\Seeders;

use App\Enums\ApplyTargetPeriod;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /** Jumlah loker per status akhir (total 40). */
    private const DISTRIBUTION = [
        'applied' => 8,
        'screening' => 6,
        'interview' => 6,
        'offer' => 3,
        'rejected' => 11,
        'ghosted' => 6,
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('DemoDataSeeder dilewati di environment production.');

            return;
        }

        DB::transaction(function () {
            $user = User::updateOrCreate(
                ['email' => 'demo@jobtracker.test'],
                [
                    'name' => 'Demo',
                    'password' => Hash::make('password'),
                    'job_search_started_at' => today()->subDays(120),
                    'apply_target' => 10,
                    'apply_target_period' => ApplyTargetPeriod::Week,
                ],
            );

            // Seeder aman dijalankan ulang: loker lama user demo dihapus dulu
            // (riwayat status dan skill gap ikut terhapus lewat cascade)
            $user->jobs()->delete();

            foreach (self::DISTRIBUTION as $status => $count) {
                for ($i = 0; $i < $count; $i++) {
                    Job::factory()
                        ->inStatus(JobStatus::from($status))
                        ->for($user)
                        ->create();
                }
            }
        });
    }
}