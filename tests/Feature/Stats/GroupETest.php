<?php

namespace Tests\Feature\Stats;

use App\Enums\JobStatus;
use App\Enums\WorkMode;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupETest extends TestCase
{
    use RefreshDatabase;

    private function job(
        User $user,
        string $city = 'Jakarta',
        WorkMode $mode = WorkMode::Onsite,
        JobStatus $status = JobStatus::Applied,
        array $history = [],
    ): Job {
        $applied = today()->subDays(10);

        $job = Job::factory()->for($user)->create([
            'city' => $city,
            'work_mode' => $mode,
            'current_status' => $status,
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

    /** Beberapa loker sekaligus untuk satu kota. */
    private function jobsIn(User $user, string $city, int $count): void
    {
        for ($n = 0; $n < $count; $n++) {
            $this->job($user, $city);
        }
    }

    // ----- #16 distribusi kota -----

    public function test_top_lima_kota_dan_sisanya_digabung_jadi_lainnya(): void
    {
        $user = User::factory()->create();

        $this->jobsIn($user, 'Jakarta', 4);
        $this->jobsIn($user, 'Bandung', 4);
        $this->jobsIn($user, 'Surabaya', 3);
        $this->jobsIn($user, 'Medan', 2);
        $this->jobsIn($user, 'Semarang', 2);
        $this->jobsIn($user, 'Malang', 1);
        $this->jobsIn($user, 'Bali', 1);

        // Seri (Jakarta/Bandung, Medan/Semarang) diurut abjad. Malang + Bali = 2 jadi "Lainnya".
        $this->actingAs($user)
            ->getJson('/stats/E')
            ->assertJsonPath('charts.city_distribution.status', 'ok')
            ->assertJsonPath('charts.city_distribution.type', 'hbar')
            ->assertJsonPath('charts.city_distribution.labels', [
                'Bandung', 'Jakarta', 'Surabaya', 'Medan', 'Semarang', 'Lainnya',
            ])
            ->assertJsonPath('charts.city_distribution.series.0.data', [4, 4, 3, 2, 2, 2]);
    }

    public function test_ejaan_kota_dinormalkan_dan_label_memakai_yang_paling_sering(): void
    {
        $user = User::factory()->create();

        $this->jobsIn($user, 'Jakarta', 2);
        $this->job($user, 'jakarta ');
        $this->job($user, '  JAKARTA');
        $this->jobsIn($user, 'Bandung', 2);

        $this->actingAs($user)
            ->getJson('/stats/E')
            ->assertJsonPath('charts.city_distribution.labels', ['Jakarta', 'Bandung'])
            ->assertJsonPath('charts.city_distribution.series.0.data', [4, 2]);
    }

    public function test_tanpa_lainnya_jika_kota_tidak_lebih_dari_lima(): void
    {
        $user = User::factory()->create();

        $this->jobsIn($user, 'Jakarta', 3);
        $this->jobsIn($user, 'Bandung', 3);

        $this->actingAs($user)
            ->getJson('/stats/E')
            ->assertJsonPath('charts.city_distribution.labels', ['Bandung', 'Jakarta']);
    }

    public function test_distribusi_kota_butuh_minimal_lima_loker(): void
    {
        $user = User::factory()->create();

        $this->jobsIn($user, 'Jakarta', 3);

        $this->actingAs($user)
            ->getJson('/stats/E')
            ->assertJsonPath('charts.city_distribution.status', 'insufficient')
            ->assertJsonPath('charts.city_distribution.current', 3)
            ->assertJsonPath('charts.city_distribution.min_required', 5);
    }

    // ----- #17 conversion per mode kerja -----

    private function seedModes(User $user): void
    {
        $a = JobStatus::Applied;
        $i = JobStatus::Interview;
        $r = JobStatus::Rejected;

        // Remote: 6 loker, 3 conversion (riwayat Interview lalu Rejected, Offer, sedang Interview)
        $this->job($user, 'Jakarta', WorkMode::Remote, $r, [[$a, 0], [$i, 3], [$r, 6]]);
        $this->job($user, 'Jakarta', WorkMode::Remote, JobStatus::Offer);
        $this->job($user, 'Jakarta', WorkMode::Remote, $i);
        for ($n = 0; $n < 3; $n++) {
            $this->job($user, 'Jakarta', WorkMode::Remote);
        }

        // Hybrid: 5 loker, 1 conversion
        $this->job($user, 'Jakarta', WorkMode::Hybrid, $i);
        for ($n = 0; $n < 4; $n++) {
            $this->job($user, 'Jakarta', WorkMode::Hybrid);
        }

        // Onsite: 2 loker (di bawah batas)
        $this->job($user, 'Jakarta', WorkMode::Onsite, $i);
        $this->job($user, 'Jakarta', WorkMode::Onsite);
    }

    public function test_conversion_per_mode_kerja(): void
    {
        $user = User::factory()->create();
        $this->seedModes($user);

        // Remote 3/6 = 50%. Hybrid 1/5 = 20%. Onsite hanya 2 sampel: kosong.
        $this->actingAs($user)
            ->getJson('/stats/E')
            ->assertJsonPath('charts.work_mode_conversion.status', 'ok')
            ->assertJsonPath('charts.work_mode_conversion.unit', '%')
            ->assertJsonPath('charts.work_mode_conversion.labels', ['Remote', 'Hybrid', 'Onsite'])
            ->assertJsonPath('charts.work_mode_conversion.series.0.data', [50, 20, null])
            ->assertJsonPath('charts.work_mode_conversion.counts', [
                ['n' => 3, 'of' => 6],
                ['n' => 1, 'of' => 5],
                ['n' => 1, 'of' => 2],
            ]);
    }

    public function test_conversion_butuh_satu_mode_dengan_minimal_lima_loker(): void
    {
        $user = User::factory()->create();

        // 6 loker tersebar 2-2-2
        foreach (WorkMode::cases() as $mode) {
            $this->job($user, 'Jakarta', $mode);
            $this->job($user, 'Jakarta', $mode);
        }

        $this->actingAs($user)
            ->getJson('/stats/E')
            ->assertJsonPath('charts.city_distribution.status', 'ok')
            ->assertJsonPath('charts.work_mode_conversion.status', 'insufficient')
            ->assertJsonPath('charts.work_mode_conversion.current', 2)
            ->assertJsonPath('charts.work_mode_conversion.min_required', 5);
    }

    public function test_data_user_lain_tidak_ikut_terhitung(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->jobsIn($other, 'Jakarta', 6);

        $this->actingAs($user)
            ->getJson('/stats/E')
            ->assertJsonPath('sample', 0)
            ->assertJsonPath('charts.city_distribution.status', 'insufficient')
            ->assertJsonPath('charts.work_mode_conversion.status', 'insufficient');
    }
}