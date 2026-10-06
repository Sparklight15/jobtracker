<?php

namespace Tests\Feature\Stats;

use App\Enums\CvCustomization;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupFTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Loker dengan atribut kelompok yang ditimpa eksplisit (default kosong/false),
     * supaya nilai acak dari factory tidak ikut terhitung.
     *
     * @param  list<string>  $skills
     * @param  list<array{JobStatus, int}>  $history
     */
    private function job(
        User $user,
        ?int $fit = null,
        ?CvCustomization $cv = null,
        bool $referral = false,
        JobStatus $status = JobStatus::Applied,
        array $skills = [],
        array $history = [],
    ): Job {
        $applied = today()->subDays(10);

        $job = Job::factory()->for($user)->create([
            'fit_score' => $fit,
            'cv_customization' => $cv,
            'has_referral' => $referral,
            'current_status' => $status,
            'applied_date' => $applied,
        ]);

        foreach ($skills as $skill) {
            $job->skillGaps()->create(['skill_name' => $skill]);
        }

        foreach ($history as [$step, $offset]) {
            $job->statusHistory()->create([
                'status' => $step,
                'changed_at' => $applied->copy()->addDays($offset),
            ]);
        }

        return $job;
    }

    // ----- #18 fit score vs hasil -----

    public function test_conversion_per_fit_score(): void
    {
        $user = User::factory()->create();

        // Skor 5: 5 loker, 3 conversion. Skor 4: 5 loker, 1 conversion. Skor 3: 2 loker (di bawah batas).
        for ($n = 0; $n < 3; $n++) {
            $this->job($user, fit: 5, status: JobStatus::Interview);
        }
        $this->job($user, fit: 5);
        $this->job($user, fit: 5);

        $this->job($user, fit: 4, status: JobStatus::Offer);
        for ($n = 0; $n < 4; $n++) {
            $this->job($user, fit: 4);
        }

        $this->job($user, fit: 3, status: JobStatus::Interview);
        $this->job($user, fit: 3);

        $this->actingAs($user)
            ->getJson('/stats/F')
            ->assertJsonPath('charts.fit_score_conversion.status', 'ok')
            ->assertJsonPath('charts.fit_score_conversion.unit', '%')
            ->assertJsonPath('charts.fit_score_conversion.labels', ['1', '2', '3', '4', '5'])
            ->assertJsonPath('charts.fit_score_conversion.series.0.data', [null, null, null, 20, 60])
            ->assertJsonPath('charts.fit_score_conversion.counts', [
                ['n' => 0, 'of' => 0],
                ['n' => 0, 'of' => 0],
                ['n' => 1, 'of' => 2],
                ['n' => 1, 'of' => 5],
                ['n' => 3, 'of' => 5],
            ]);
    }

    public function test_fit_score_butuh_satu_skor_dengan_minimal_lima_loker(): void
    {
        $user = User::factory()->create();

        // Loker tanpa fit score tidak ikut kelompok mana pun
        for ($n = 0; $n < 6; $n++) {
            $this->job($user);
        }

        $this->actingAs($user)
            ->getJson('/stats/F')
            ->assertJsonPath('charts.fit_score_conversion.status', 'insufficient')
            ->assertJsonPath('charts.fit_score_conversion.current', 0)
            ->assertJsonPath('charts.fit_score_conversion.min_required', 5);
    }

    // ----- #19 CV disesuaikan vs generik -----

    public function test_conversion_cv_disesuaikan_vs_generik(): void
    {
        $user = User::factory()->create();

        // Generik: 6 loker, 1 conversion lewat riwayat (sekarang Rejected). Disesuaikan: 5 loker, 3 conversion.
        $this->job($user, cv: CvCustomization::Generic, status: JobStatus::Rejected, history: [
            [JobStatus::Applied, 0], [JobStatus::Interview, 3], [JobStatus::Rejected, 6],
        ]);
        for ($n = 0; $n < 5; $n++) {
            $this->job($user, cv: CvCustomization::Generic);
        }

        for ($n = 0; $n < 3; $n++) {
            $this->job($user, cv: CvCustomization::Tailored, status: JobStatus::Interview);
        }
        $this->job($user, cv: CvCustomization::Tailored);
        $this->job($user, cv: CvCustomization::Tailored);

        $this->actingAs($user)
            ->getJson('/stats/F')
            ->assertJsonPath('charts.cv_conversion.status', 'ok')
            ->assertJsonPath('charts.cv_conversion.labels', ['Generik', 'Disesuaikan'])
            ->assertJsonPath('charts.cv_conversion.series.0.data', [16.7, 60])
            ->assertJsonPath('charts.cv_conversion.counts', [
                ['n' => 1, 'of' => 6],
                ['n' => 3, 'of' => 5],
            ]);
    }

    // ----- #20 skill gap -----

    public function test_skill_yang_paling_sering_kurang(): void
    {
        $user = User::factory()->create();

        $this->job($user, skills: ['Python', 'SQL']);
        $this->job($user, skills: ['Python', 'Docker']);
        $this->job($user, skills: ['python', 'SQL']);
        $this->job($user, skills: ['Docker']);
        $this->job($user, skills: ['Kubernetes']);

        // Python 3 (ejaan terbanyak "Python"). SQL dan Docker seri 2 diurut abjad. Kubernetes 1.
        $this->actingAs($user)
            ->getJson('/stats/F')
            ->assertJsonPath('charts.skill_gaps.status', 'ok')
            ->assertJsonPath('charts.skill_gaps.type', 'hbar')
            ->assertJsonPath('charts.skill_gaps.labels', ['Python', 'Docker', 'SQL', 'Kubernetes'])
            ->assertJsonPath('charts.skill_gaps.series.0.data', [3, 2, 2, 1]);
    }

    public function test_skill_gap_hanya_sepuluh_teratas(): void
    {
        $user = User::factory()->create();

        for ($n = 1; $n <= 12; $n++) {
            $this->job($user, skills: [sprintf('Skill %02d', $n)]);
        }

        $labels = $this->actingAs($user)->getJson('/stats/F')->json('charts.skill_gaps.labels');

        $this->assertCount(10, $labels);
        $this->assertSame('Skill 01', $labels[0]);
        $this->assertSame('Skill 10', $labels[9]);
    }

    public function test_skill_gap_butuh_minimal_lima_data_skill(): void
    {
        $user = User::factory()->create();

        $this->job($user, skills: ['Python', 'SQL']);
        $this->job($user, skills: ['Docker']);

        $this->actingAs($user)
            ->getJson('/stats/F')
            ->assertJsonPath('charts.skill_gaps.status', 'insufficient')
            ->assertJsonPath('charts.skill_gaps.current', 3)
            ->assertJsonPath('charts.skill_gaps.min_required', 5);
    }

    // ----- #21 referral vs cold apply -----

    public function test_conversion_dengan_referral_vs_tanpa(): void
    {
        $user = User::factory()->create();

        // Dengan referral: 5 loker, 2 conversion. Tanpa: 6 loker, 1 conversion.
        $this->job($user, referral: true, status: JobStatus::Offer);
        $this->job($user, referral: true, status: JobStatus::Interview);
        for ($n = 0; $n < 3; $n++) {
            $this->job($user, referral: true);
        }

        $this->job($user, status: JobStatus::Interview);
        for ($n = 0; $n < 5; $n++) {
            $this->job($user);
        }

        $this->actingAs($user)
            ->getJson('/stats/F')
            ->assertJsonPath('charts.referral_conversion.status', 'ok')
            ->assertJsonPath('charts.referral_conversion.labels', ['Dengan referral', 'Tanpa referral'])
            ->assertJsonPath('charts.referral_conversion.series.0.data', [40, 16.7])
            ->assertJsonPath('charts.referral_conversion.counts', [
                ['n' => 2, 'of' => 5],
                ['n' => 1, 'of' => 6],
            ]);
    }

    public function test_referral_butuh_satu_kelompok_dengan_minimal_lima_loker(): void
    {
        $user = User::factory()->create();

        $this->job($user, referral: true);
        $this->job($user, referral: true);
        $this->job($user);
        $this->job($user);

        $this->actingAs($user)
            ->getJson('/stats/F')
            ->assertJsonPath('charts.referral_conversion.status', 'insufficient')
            ->assertJsonPath('charts.referral_conversion.current', 2)
            ->assertJsonPath('charts.referral_conversion.min_required', 5);
    }

    public function test_data_user_lain_tidak_ikut_terhitung(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        for ($n = 0; $n < 6; $n++) {
            $this->job($other, fit: 5, cv: CvCustomization::Tailored, referral: true, skills: ["Skill {$n}"]);
        }

        $this->actingAs($user)
            ->getJson('/stats/F')
            ->assertJsonPath('sample', 0)
            ->assertJsonPath('charts.fit_score_conversion.status', 'insufficient')
            ->assertJsonPath('charts.cv_conversion.status', 'insufficient')
            ->assertJsonPath('charts.skill_gaps.status', 'insufficient')
            ->assertJsonPath('charts.referral_conversion.status', 'insufficient');
    }
}