<?php

namespace Tests\Feature\Stats;

use App\Enums\IndustrySector;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupDTest extends TestCase
{
    use RefreshDatabase;

    /** Tiga sektor pertama dari enum, supaya test tidak bergantung pada nama case. */
    private function sectors(): array
    {
        $cases = IndustrySector::cases();

        $this->assertGreaterThanOrEqual(3, count($cases), 'Test butuh minimal 3 sektor di enum.');

        return array_slice($cases, 0, 3);
    }

    /**
     * Loker dengan sektor dan status tetap. salary_offered selalu ditimpa (default null)
     * supaya nilai acak dari factory tidak ikut terhitung.
     */
    private function job(
        User $user,
        IndustrySector $sector,
        JobStatus $status = JobStatus::Applied,
        ?int $salary = null,
        array $history = [],
    ): Job {
        $applied = today()->subDays(10);

        $job = Job::factory()->for($user)->create([
            'industry_sector' => $sector,
            'current_status' => $status,
            'salary_offered' => $salary,
            'applied_date' => $applied,
        ]);

        foreach ($history as [$step, $offset]) {
            $job->statusHistory()->create([
                'status' => $step,
                'changed_at' => $applied->copy()->addDays($offset),
            ]);
        }

        return $job;
    }

    private function jobsIn(User $user, IndustrySector $sector, int $count): void
    {
        for ($n = 0; $n < $count; $n++) {
            $this->job($user, $sector);
        }
    }

    /**
     * S0: 6 loker. 3 Offer (gaji 10jt, 12jt, 14jt), 3 Applied. Conversion 3/6.
     * S1: 5 loker. 2 Offer (gaji 20jt, 30jt), 3 Applied. Conversion 2/5.
     * S2: 2 loker. 1 Interview, 1 Applied. Di bawah batas.
     */
    private function seedSectors(User $user): void
    {
        [$s0, $s1, $s2] = $this->sectors();

        foreach ([10_000_000, 12_000_000, 14_000_000] as $salary) {
            $this->job($user, $s0, JobStatus::Offer, $salary);
        }
        $this->jobsIn($user, $s0, 3);

        foreach ([20_000_000, 30_000_000] as $salary) {
            $this->job($user, $s1, JobStatus::Offer, $salary);
        }
        $this->jobsIn($user, $s1, 3);

        $this->job($user, $s2, JobStatus::Interview);
        $this->job($user, $s2);
    }

    // ----- #13 distribusi -----

    public function test_distribusi_per_sektor_urut_terbanyak(): void
    {
        $user = User::factory()->create();
        $this->seedSectors($user);
        [$s0, $s1, $s2] = $this->sectors();

        $this->actingAs($user)
            ->getJson('/stats/D')
            ->assertJsonPath('charts.sector_distribution.status', 'ok')
            ->assertJsonPath('charts.sector_distribution.type', 'hbar')
            ->assertJsonPath('charts.sector_distribution.labels', [$s0->value, $s1->value, $s2->value])
            ->assertJsonPath('charts.sector_distribution.series.0.data', [6, 5, 2]);
    }

    public function test_distribusi_butuh_minimal_lima_loker(): void
    {
        $user = User::factory()->create();
        [$s0] = $this->sectors();

        $this->jobsIn($user, $s0, 3);

        $this->actingAs($user)
            ->getJson('/stats/D')
            ->assertJsonPath('charts.sector_distribution.status', 'insufficient')
            ->assertJsonPath('charts.sector_distribution.current', 3)
            ->assertJsonPath('charts.sector_distribution.min_required', 5);
    }

    // ----- #14 conversion -----

    public function test_conversion_per_sektor(): void
    {
        $user = User::factory()->create();
        $this->seedSectors($user);
        [$s0, $s1, $s2] = $this->sectors();

        // S0 3/6 = 50%. S1 2/5 = 40%. S2 hanya 2 sampel: kosong.
        $this->actingAs($user)
            ->getJson('/stats/D')
            ->assertJsonPath('charts.sector_conversion.status', 'ok')
            ->assertJsonPath('charts.sector_conversion.unit', '%')
            ->assertJsonPath('charts.sector_conversion.labels', [$s0->value, $s1->value, $s2->value])
            ->assertJsonPath('charts.sector_conversion.series.0.data', [50, 40, null])
            ->assertJsonPath('charts.sector_conversion.counts', [
                ['n' => 3, 'of' => 6],
                ['n' => 2, 'of' => 5],
                ['n' => 1, 'of' => 2],
            ]);
    }

    public function test_conversion_menghitung_pernah_interview_dari_riwayat(): void
    {
        $user = User::factory()->create();
        [$s0] = $this->sectors();

        // Rejected sekarang, tapi pernah Interview: tetap conversion. 1 dari 5.
        $this->job($user, $s0, JobStatus::Rejected, null, [
            [JobStatus::Applied, 0], [JobStatus::Interview, 3], [JobStatus::Rejected, 6],
        ]);
        $this->jobsIn($user, $s0, 4);

        $this->actingAs($user)
            ->getJson('/stats/D')
            ->assertJsonPath('charts.sector_conversion.series.0.data', [20])
            ->assertJsonPath('charts.sector_conversion.counts', [['n' => 1, 'of' => 5]]);
    }

    public function test_conversion_butuh_satu_sektor_dengan_minimal_lima_loker(): void
    {
        $user = User::factory()->create();

        // 6 loker tersebar 2-2-2
        foreach ($this->sectors() as $sector) {
            $this->jobsIn($user, $sector, 2);
        }

        $this->actingAs($user)
            ->getJson('/stats/D')
            ->assertJsonPath('charts.sector_distribution.status', 'ok')
            ->assertJsonPath('charts.sector_conversion.status', 'insufficient')
            ->assertJsonPath('charts.sector_conversion.current', 2)
            ->assertJsonPath('charts.sector_conversion.min_required', 5);
    }

    // ----- #15 rata-rata gaji -----

    public function test_rata_rata_gaji_hanya_sektor_dengan_sampel_cukup(): void
    {
        $user = User::factory()->create();
        $this->seedSectors($user);
        [$s0, $s1] = $this->sectors();

        // S0: 3 gaji, rata-rata 12jt. S1: 2 gaji (di bawah batas 3): kosong. S2 tanpa gaji: tidak tampil.
        $this->actingAs($user)
            ->getJson('/stats/D')
            ->assertJsonPath('charts.sector_salary.status', 'ok')
            ->assertJsonPath('charts.sector_salary.unit', 'Rp')
            ->assertJsonPath('charts.sector_salary.labels', [$s0->value, $s1->value])
            ->assertJsonPath('charts.sector_salary.series.0.data', [12_000_000, null])
            ->assertJsonPath('charts.sector_salary.counts', [['n' => 3], ['n' => 2]]);
    }

    public function test_gaji_butuh_minimal_tiga_gaji_ditawarkan(): void
    {
        $user = User::factory()->create();
        [$s0] = $this->sectors();

        // 6 loker tanpa satu pun gaji ditawarkan
        $this->jobsIn($user, $s0, 6);

        $this->actingAs($user)
            ->getJson('/stats/D')
            ->assertJsonPath('charts.sector_salary.status', 'insufficient')
            ->assertJsonPath('charts.sector_salary.current', 0)
            ->assertJsonPath('charts.sector_salary.min_required', 3);
    }

    public function test_data_user_lain_tidak_ikut_terhitung(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        [$s0] = $this->sectors();

        $this->jobsIn($other, $s0, 6);

        $this->actingAs($user)
            ->getJson('/stats/D')
            ->assertJsonPath('sample', 0)
            ->assertJsonPath('charts.sector_distribution.status', 'insufficient')
            ->assertJsonPath('charts.sector_conversion.status', 'insufficient')
            ->assertJsonPath('charts.sector_salary.status', 'insufficient');
    }
}