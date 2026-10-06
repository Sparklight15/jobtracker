<?php

namespace Tests\Feature\Stats;

use App\Enums\ApplyTargetPeriod;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupATest extends TestCase
{
    use RefreshDatabase;

    /** Satu loker dengan riwayat status berurutan, apply 5 hari lalu. */
    private function makeJob(User $user, array $path): Job
    {
        $job = Job::factory()->for($user)->create([
            'current_status' => end($path),
            'applied_date' => today()->subDays(5),
        ]);

        foreach ($path as $i => $status) {
            $job->statusHistory()->create([
                'status' => $status,
                'changed_at' => today()->subDays(5 - $i),
            ]);
        }

        return $job;
    }

    /**
     * 10 loker dengan hasil yang bisa dihitung tangan:
     *   pernah mencapai: Applied 10, Screening 7, Interview 4, Offer 2
     *   (loker #8 loncat Screening tapi tetap dihitung lolos Screening)
     *   status saat ini: applied 2, screening 1, interview 1, offer 2, rejected 2, ghosted 2
     */
    private function seedFunnel(User $user): void
    {
        $a = JobStatus::Applied;
        $s = JobStatus::Screening;
        $i = JobStatus::Interview;
        $o = JobStatus::Offer;
        $r = JobStatus::Rejected;
        $g = JobStatus::Ghosted;

        $this->makeJob($user, [$a]);
        $this->makeJob($user, [$a]);
        $this->makeJob($user, [$a, $s]);
        $this->makeJob($user, [$a, $s, $r]);
        $this->makeJob($user, [$a, $s, $i]);
        $this->makeJob($user, [$a, $s, $i, $r]);
        $this->makeJob($user, [$a, $s, $i, $o]);
        $this->makeJob($user, [$a, $i, $o]);
        $this->makeJob($user, [$a, $g]);
        $this->makeJob($user, [$a, $s, $g]);
    }

    public function test_jumlah_per_tahap_menghitung_pernah_mencapai(): void
    {
        $user = User::factory()->create();
        $this->seedFunnel($user);

        $this->actingAs($user)
            ->getJson('/stats/A')
            ->assertJsonPath('sample', 10)
            ->assertJsonPath('charts.per_stage.labels', ['Applied', 'Screening', 'Interview', 'Offer'])
            ->assertJsonPath('charts.per_stage.series.0.data', [10, 7, 4, 2]);
    }

    public function test_conversion_antar_tahap(): void
    {
        $user = User::factory()->create();
        $this->seedFunnel($user);

        // JSON menulis 70.0 sebagai 70, jadi setelah di-decode berupa integer
        $this->actingAs($user)
            ->getJson('/stats/A')
            ->assertJsonPath('charts.stage_conversion.unit', '%')
            ->assertJsonPath('charts.stage_conversion.series.0.data', [70, 57.1, 50]);
    }

    public function test_overall_conversion_dan_ghosting(): void
    {
        $user = User::factory()->create();
        $this->seedFunnel($user);

        $this->actingAs($user)
            ->getJson('/stats/A')
            ->assertJsonPath('cards.overall_conversion.value', 20)
            ->assertJsonPath('cards.overall_conversion.note', '2 dari 10 loker sampai tahap Offer')
            ->assertJsonPath('cards.ghosting_rate.value', 20)
            ->assertJsonPath('cards.ghosting_rate.note', '2 dari 10 loker tanpa kabar');
    }

    public function test_status_saat_ini(): void
    {
        $user = User::factory()->create();
        $this->seedFunnel($user);

        $this->actingAs($user)
            ->getJson('/stats/A')
            ->assertJsonPath('charts.status_now.series.0.data', [2, 1, 1, 2, 2, 2]);
    }

    public function test_data_belum_cukup_di_bawah_ambang(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 4) as $_) {
            $this->makeJob($user, [JobStatus::Applied]);
        }

        $this->actingAs($user)
            ->getJson('/stats/A')
            ->assertJsonPath('cards.total_apply.status', 'ok')
            ->assertJsonPath('cards.total_apply.value', 4)
            ->assertJsonPath('cards.overall_conversion.status', 'insufficient')
            ->assertJsonPath('cards.overall_conversion.current', 4)
            ->assertJsonPath('cards.overall_conversion.min_required', 5)
            ->assertJsonPath('cards.ghosting_rate.status', 'insufficient')
            ->assertJsonPath('charts.stage_conversion.status', 'insufficient')
            ->assertJsonPath('charts.per_stage.status', 'insufficient');
    }

    public function test_target_belum_diatur(): void
    {
        $user = User::factory()->create(['apply_target' => null, 'apply_target_period' => null]);
        $this->seedFunnel($user);

        $this->actingAs($user)
            ->getJson('/stats/A')
            ->assertJsonPath('cards.total_apply.note', 'Target belum diatur.')
            ->assertJsonMissingPath('cards.total_apply.target');
    }

    public function test_target_per_minggu_diskalakan_ke_periode(): void
    {
        // 10 per minggu x 30 hari / 7 = 42,86 -> 43; 10 / 43 = 23%
        $user = User::factory()->create(['apply_target' => 10, 'apply_target_period' => ApplyTargetPeriod::Week]);
        $this->seedFunnel($user);

        $this->actingAs($user)
            ->getJson('/stats/A?period=30d')
            ->assertJsonPath('cards.total_apply.target', 43)
            ->assertJsonPath('cards.total_apply.note', 'Target periode ini: 43 loker (23%)');
    }

    public function test_target_per_bulan_diskalakan_ke_periode(): void
    {
        // 30 per bulan x 30 hari / 30 = 30; 10 / 30 = 33%
        $user = User::factory()->create(['apply_target' => 30, 'apply_target_period' => ApplyTargetPeriod::Month]);
        $this->seedFunnel($user);

        $this->actingAs($user)
            ->getJson('/stats/A?period=30d')
            ->assertJsonPath('cards.total_apply.target', 30)
            ->assertJsonPath('cards.total_apply.note', 'Target periode ini: 30 loker (33%)');
    }

    public function test_target_periode_semua_dihitung_dari_awal_pencarian(): void
    {
        // Mulai 59 hari lalu -> 60 hari inklusif; 7 per minggu = 1 per hari -> 60; 10 / 60 = 17%
        $user = User::factory()->create([
            'apply_target' => 7,
            'apply_target_period' => ApplyTargetPeriod::Week,
            'job_search_started_at' => today()->subDays(59),
        ]);
        $this->seedFunnel($user);

        $this->actingAs($user)
            ->getJson('/stats/A?period=all')
            ->assertJsonPath('cards.total_apply.target', 60)
            ->assertJsonPath('cards.total_apply.note', 'Target periode ini: 60 loker (17%)');
    }
}