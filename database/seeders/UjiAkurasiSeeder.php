<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Enums\CvCustomization;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UjiAkurasiSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat akun uji khusus
        $user = User::firstOrCreate(
            ['email' => 'uji@jobtracker.com'],
            [
                'name' => 'Akun Uji Akurasi',
                'password' => Hash::make('password'),
                'job_search_started_at' => now()->subDays(30),
            ]
        );

        // Bersihkan data lama jika seeder ini dijalankan ulang
        Job::where('user_id', $user->id)->delete();

        // 2. Buat 10 Loker dengan skenario pasti untuk validasi manual
        
        // Skenario 1-5: Tembus sampai Interview, 2 dapet Offer (Conversion Rate Offer = 20%)
        for ($i = 1; $i <= 5; $i++) {
            $status = $i <= 2 ? JobStatus::Offer : JobStatus::Interview;
            $job = Job::create([
                'user_id' => $user->id,
                'company_name' => "PT Tembus $i",
                'position' => 'Software Engineer',
                'current_status' => $status,
                'applied_date' => now()->subDays(25),
                'channel' => Channel::Referral,
                'cv_customization' => CvCustomization::Tailored,
            ]);

            $job->statusHistory()->createMany([
                ['status' => JobStatus::Applied, 'changed_at' => now()->subDays(25)],
                ['status' => JobStatus::Screening, 'changed_at' => now()->subDays(20)],
                ['status' => JobStatus::Interview, 'changed_at' => now()->subDays(15)],
            ]);

            if ($status === JobStatus::Offer) {
                $job->statusHistory()->create(['status' => JobStatus::Offer, 'changed_at' => now()->subDays(10)]);
            }
        }

        // Skenario 6-8: Ghosted di tahap Applied (Rasio Ghosting = 30%)
        for ($i = 6; $i <= 8; $i++) {
            $job = Job::create([
                'user_id' => $user->id,
                'company_name' => "PT Ghosting $i",
                'position' => 'Data Analyst',
                'current_status' => JobStatus::Ghosted,
                'applied_date' => now()->subDays(15),
                'channel' => Channel::JobPortal, // Diperbarui dari JobBoard
                'cv_customization' => CvCustomization::Generic,
            ]);

            $job->statusHistory()->createMany([
                ['status' => JobStatus::Applied, 'changed_at' => now()->subDays(15)],
                ['status' => JobStatus::Ghosted, 'changed_at' => now()],
            ]);
        }

        // Skenario 9-10: Ditolak di Screening
        for ($i = 9; $i <= 10; $i++) {
            $job = Job::create([
                'user_id' => $user->id,
                'company_name' => "PT Tolak $i",
                'position' => 'Product Manager',
                'current_status' => JobStatus::Rejected,
                'applied_date' => now()->subDays(10),
                'channel' => Channel::JobPortal, // Diperbarui menjadi JobPortal agar aman dari error enum
                'cv_customization' => CvCustomization::Generic,
            ]);

            $job->statusHistory()->createMany([
                ['status' => JobStatus::Applied, 'changed_at' => now()->subDays(10)],
                ['status' => JobStatus::Screening, 'changed_at' => now()->subDays(8)],
                ['status' => JobStatus::Rejected, 'changed_at' => now()->subDays(5)],
            ]);
        }
    }
}