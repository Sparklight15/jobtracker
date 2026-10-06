<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Enums\CvCustomization;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LokerDummySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'findingsparklight@gmail.com')->first();
        
        if (!$user) {
            $user = User::create([
                'name' => 'Finding Sparklight',
                'email' => 'findingsparklight@gmail.com',
                'password' => Hash::make('iffazya15'),
                'job_search_started_at' => now()->subDays(120),
            ]);
        } else {
            if (!$user->job_search_started_at) {
                $user->update(['job_search_started_at' => now()->subDays(120)]);
            }
            Job::where('user_id', $user->id)->delete();
        }

        $sectors = ['Teknologi', 'Jasa/Konsultan', 'Kesehatan', 'Retail/FMCG', 'Keuangan'];
        $cities = ['Jakarta', 'Bandung', 'Bekasi', 'Tangerang', 'Surabaya', 'Yogyakarta'];
        $modes = ['onsite', 'remote', 'hybrid'];
        $channels = [Channel::JobPortal, Channel::Referral];
        $cvs = [CvCustomization::Tailored, CvCustomization::Generic];
        $skills = ['Komunikasi Bahasa Inggris', 'Pengalaman spesifik', 'Tools teknis (Docker/AWS)', 'Manajemen Proyek', 'Data Analysis'];

        // Generate 120 Loker 
        for ($i = 1; $i <= 120; $i++) {
            $appliedDaysAgo = rand(5, 100);
            $appliedDate = now()->subDays($appliedDaysAgo);
            
            $rand = rand(1, 100);
            // Kita naikkan rasio Offer menjadi 30% agar pasti tembus minimum 3 per sektor
            if ($rand <= 30) {
                $status = JobStatus::Offer;
            } elseif ($rand <= 50) {
                $status = JobStatus::Rejected;
            } elseif ($rand <= 70) {
                $status = JobStatus::Ghosted;
            } elseif ($rand <= 85) {
                $status = JobStatus::Interview;
            } elseif ($rand <= 95) {
                $status = JobStatus::Screening;
            } else {
                $status = JobStatus::Applied;
            }

            // Atur nominal gaji offer jika statusnya Offer
            $isOffer = $status === JobStatus::Offer;
            $minSal = rand(7, 12) * 1000000;
            
            $job = Job::create([
                'user_id' => $user->id,
                'company_name' => "Perusahaan Tech " . $i,
                'position' => ['Software Engineer', 'Data Analyst', 'Product Manager', 'UI/UX Designer', 'DevOps'][array_rand([0,1,2,3,4])],
                'current_status' => $status,
                'applied_date' => $appliedDate,
                'channel' => $channels[array_rand($channels)],
                'cv_customization' => $cvs[array_rand($cvs)],
                'industry_sector' => $sectors[array_rand($sectors)],
                'city' => $cities[array_rand($cities)],
                'work_mode' => $modes[array_rand($modes)],
                'salary_min' => $minSal,
                'salary_max' => $minSal + (rand(5, 15) * 1000000),
                // PERBAIKAN: Menambahkan nominal gaji yang ditawarkan
                'salary_offered' => $isOffer ? ($minSal + (rand(2, 10) * 1000000)) : null,
                'fit_score' => rand(2, 5),
                'skill_match_score' => rand(2, 5),
            ]);

            if (rand(1, 100) <= 60) { 
                DB::table('job_skill_gaps')->insert([
                    'job_id' => $job->id,
                    'skill_name' => $skills[array_rand($skills)],
                ]);
            }

            $job->statusHistory()->create(['status' => JobStatus::Applied, 'changed_at' => $appliedDate]);
            $currentDaysAgo = $appliedDaysAgo;

            if (in_array($status, [JobStatus::Screening, JobStatus::Interview, JobStatus::Offer, JobStatus::Rejected])) {
                $currentDaysAgo -= rand(2, 5);
                if ($currentDaysAgo > 0) $job->statusHistory()->create(['status' => JobStatus::Screening, 'changed_at' => now()->subDays($currentDaysAgo)]);
            }

            if (in_array($status, [JobStatus::Interview, JobStatus::Offer])) {
                $currentDaysAgo -= rand(3, 10);
                if ($currentDaysAgo > 0) $job->statusHistory()->create(['status' => JobStatus::Interview, 'changed_at' => now()->subDays($currentDaysAgo)]);
            }

            if ($status === JobStatus::Offer) {
                $job->statusHistory()->create(['status' => JobStatus::Offer, 'changed_at' => now()->subDays(max(0, $currentDaysAgo - rand(2, 7)))]);
            } elseif ($status === JobStatus::Rejected) {
                $job->statusHistory()->create(['status' => JobStatus::Rejected, 'changed_at' => now()->subDays(max(0, $currentDaysAgo - rand(1, 5)))]);
            } elseif ($status === JobStatus::Ghosted) {
                $job->statusHistory()->create(['status' => JobStatus::Ghosted, 'changed_at' => now()->subDays(max(0, $currentDaysAgo - rand(14, 21)))]);
            }
        }
    }
}