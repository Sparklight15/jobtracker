<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\CvCustomization;
use App\Enums\IndustrySector;
use App\Enums\JobStatus;
use App\Enums\OfferDecision;
use App\Enums\RejectionReason;
use App\Enums\WorkMode;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    private const POSITIONS = [
        'Backend Developer', 'Frontend Developer', 'Fullstack Developer',
        'Data Analyst', 'UI/UX Designer', 'Product Manager', 'QA Engineer',
        'DevOps Engineer', 'Business Analyst', 'Project Manager',
        'Digital Marketing Specialist', 'Content Strategist',
    ];

    private const CITIES = [
        'Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Medan',
        'Tangerang', 'Bekasi', 'Depok', 'Bogor', 'Malang', 'Denpasar',
    ];

    private const NOTES = [
        'Proses cepat, HR ramah.',
        'Perlu siapkan portofolio sebelum tahap berikutnya.',
        'Ada tes teknis online.',
        'Follow up lewat email minggu depan.',
        'Gaji belum dibahas di tahap ini.',
        'Lokasi kantor cukup jauh dari rumah.',
    ];

    private const SKILLS = [
        'Docker', 'Kubernetes', 'AWS', 'TypeScript', 'React', 'Laravel',
        'SQL', 'Python', 'Figma', 'Tableau', 'Public speaking', 'English',
    ];

    /**
     * Atribut dasar. Atribut yang bergantung pada status (status, tanggal,
     * data offer, alasan rejection) diisi lewat inStatus().
     */
    public function definition(): array
    {
        $channel = Channel::from($this->weighted([
            'job_portal' => 30,
            'linkedin' => 25,
            'company_website' => 15,
            'cold_apply' => 10,
            'referral' => 8,
            'recruiter_reach_out' => 8,
            'other' => 4,
        ]));

        $hasReferral = $channel === Channel::Referral || mt_rand(1, 100) <= 8;

        $hasSalary = mt_rand(1, 100) <= 60;
        $salaryMin = $hasSalary ? mt_rand(12, 40) * 500_000 : null;
        $salaryMax = $hasSalary ? $salaryMin + mt_rand(4, 16) * 500_000 : null;

        return [
            'company_name' => fake()->company(),
            'position' => fake()->randomElement(self::POSITIONS),
            'job_url' => fake()->optional(0.6)->url(),
            'current_status' => JobStatus::Applied,
            'applied_date' => today(),
            'channel' => $channel,
            'has_referral' => $hasReferral,
            'referrer_name' => $hasReferral ? fake()->name() : null,
            'industry_sector' => fake()->randomElement(IndustrySector::cases()),
            'city' => fake()->randomElement(self::CITIES),
            'work_mode' => fake()->randomElement(WorkMode::cases()),
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'fit_score' => fake()->optional(0.85)->numberBetween(1, 5),
            'cv_customization' => fake()->optional(0.8)->randomElement(CvCustomization::cases()),
            'skill_match_score' => fake()->optional(0.85)->numberBetween(1, 5),
            'notes' => fake()->optional(0.3)->randomElement(self::NOTES),
        ];
    }

    /**
     * Buat loker dengan status akhir tertentu, lengkap dengan riwayat status
     * yang konsisten (dan skill gap) setelah loker tersimpan.
     */
    public function inStatus(JobStatus $status): static
    {
        $timeline = $this->buildTimeline($status);

        return $this
            ->state(fn (array $attributes) => $this->statusAttributes($status, $timeline, $attributes))
            ->afterCreating(fn (Job $job) => $this->storeTimeline($job, $timeline));
    }

    /**
     * Susun daftar [status, tanggal] dari status awal sampai status akhir.
     */
    private function buildTimeline(JobStatus $status): array
    {
        $stages = [JobStatus::Applied, JobStatus::Screening, JobStatus::Interview];

        $path = match ($status) {
            JobStatus::Applied => [JobStatus::Applied],
            JobStatus::Screening => array_slice($stages, 0, 2),
            JobStatus::Interview => $stages,
            JobStatus::Offer => [...$stages, JobStatus::Offer],
            JobStatus::Rejected, JobStatus::Ghosted => [...array_slice($stages, 0, mt_rand(1, 3)), $status],
        };

        // Berapa hari lalu status terakhir terjadi
        $daysAgo = match ($status) {
            JobStatus::Applied => mt_rand(0, 21),
            JobStatus::Screening, JobStatus::Interview => mt_rand(0, 25),
            JobStatus::Offer => mt_rand(0, 30),
            JobStatus::Rejected, JobStatus::Ghosted => mt_rand(0, 75),
        };

        $date = today()->subDays($daysAgo);
        $timeline = [];

        // Disusun mundur dari status terakhir supaya tidak ada tanggal di masa depan
        for ($i = count($path) - 1; $i >= 0; $i--) {
            $timeline[] = ['status' => $path[$i], 'date' => $date->copy()];

            if ($i > 0) {
                $gap = $path[$i] === JobStatus::Ghosted ? mt_rand(14, 30) : mt_rand(2, 14);
                $date->subDays($gap);
            }
        }

        return array_reverse($timeline);
    }

    private function statusAttributes(JobStatus $status, array $timeline, array $attributes): array
    {
        // Respons pertama = tahap pertama setelah applied yang bukan ghosted
        $firstResponse = collect($timeline)
            ->skip(1)
            ->first(fn (array $step) => $step['status'] !== JobStatus::Ghosted);

        return [
            'current_status' => $status,
            'applied_date' => $timeline[0]['date'],
            'first_response_date' => $firstResponse['date'] ?? null,
            'rejection_reason' => $status === JobStatus::Rejected
                ? fake()->randomElement(RejectionReason::cases())
                : null,
            'offer_decision' => $status === JobStatus::Offer
                ? OfferDecision::from($this->weighted(['pending' => 40, 'accepted' => 35, 'declined' => 25]))
                : null,
            'salary_offered' => $status === JobStatus::Offer
                ? $this->offeredSalary($attributes)
                : null,
        ];
    }

    private function offeredSalary(array $attributes): int
    {
        if ($attributes['salary_min'] !== null && $attributes['salary_max'] !== null) {
            return mt_rand(
                intdiv($attributes['salary_min'], 500_000),
                intdiv($attributes['salary_max'], 500_000),
            ) * 500_000;
        }

        return mt_rand(16, 50) * 500_000;
    }

    private function storeTimeline(Job $job, array $timeline): void
    {
        foreach ($timeline as $step) {
            $job->statusHistory()->create([
                'status' => $step['status'],
                'changed_at' => $step['date'],
            ]);
        }

        // Skor kecocokan rendah berarti lebih banyak skill yang kurang
        $gapCount = ($job->skill_match_score !== null && $job->skill_match_score <= 3)
            ? mt_rand(1, 3)
            : mt_rand(0, 1);

        if ($gapCount > 0) {
            $skills = collect(self::SKILLS)->random($gapCount);

            $job->skillGaps()->createMany(
                $skills->map(fn (string $skill) => ['skill_name' => $skill])->all()
            );
        }
    }

    /**
     * Pilih satu kunci secara acak dengan bobot.
     */
    private function weighted(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weights);
    }
}