<?php

namespace Tests\Feature\Stats;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupBTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Loker dengan riwayat berupa daftar [status, hari setelah apply].
     */
    private function jobWithHistory(User $user, int $appliedDaysAgo, array $steps): Job
    {
        $applied = today()->subDays($appliedDaysAgo);

        $job = Job::factory()->for($user)->create([
            'applied_date' => $applied,
            'current_status' => end($steps)[0],
        ]);

        foreach ($steps as [$status, $offset]) {
            $job->statusHistory()->create([
                'status' => $status,
                'changed_at' => $applied->copy()->addDays($offset),
            ]);
        }

        return $job;
    }

    private function respondedJob(User $user, int $days): Job
    {
        return Job::factory()->for($user)->create([
            'applied_date' => today()->subDays(20),
            'first_response_date' => today()->subDays(20)->addDays($days),
        ]);
    }

    // ----- #6 waktu ke respons pertama -----

    public function test_waktu_ke_respons_pertama_rata_rata_dan_median(): void
    {
        $user = User::factory()->create();

        // 2, 4, 6, 8, 10 hari: rata-rata 6, median 6. Dua loker tanpa respons tidak ikut.
        foreach ([2, 4, 6, 8, 10] as $days) {
            $this->respondedJob($user, $days);
        }
        Job::factory()->count(2)->for($user)->create(['applied_date' => today()->subDays(20)]);

        $this->actingAs($user)
            ->getJson('/stats/B')
            ->assertJsonPath('cards.first_response_days.status', 'ok')
            ->assertJsonPath('cards.first_response_days.value', 6)
            ->assertJsonPath('cards.first_response_days.unit', 'hari')
            ->assertJsonPath('cards.first_response_days.note', 'Median 6 hari · dari 5 loker yang direspons');
    }

    public function test_waktu_respons_butuh_minimal_tiga_loker_yang_direspons(): void
    {
        $user = User::factory()->create();

        $this->respondedJob($user, 3);
        $this->respondedJob($user, 5);
        Job::factory()->count(4)->for($user)->create(['applied_date' => today()->subDays(20)]);

        $this->actingAs($user)
            ->getJson('/stats/B')
            ->assertJsonPath('cards.first_response_days.status', 'insufficient')
            ->assertJsonPath('cards.first_response_days.current', 2)
            ->assertJsonPath('cards.first_response_days.min_required', 3);
    }

    // ----- #7 waktu per tahap -----

    public function test_lama_tiap_tahap_hanya_tahap_selesai_dan_tanpa_ghosted(): void
    {
        $user = User::factory()->create();

        $a = JobStatus::Applied;
        $s = JobStatus::Screening;
        $i = JobStatus::Interview;
        $o = JobStatus::Offer;
        $r = JobStatus::Rejected;
        $g = JobStatus::Ghosted;

        // Applied: 2, 4, 6, 8 (rata-rata 5). Screening: 3, 2, 3 (rata-rata 2,67). Interview: 4, 2 (hanya 2 sampel).
        $this->jobWithHistory($user, 40, [[$a, 0], [$s, 2], [$i, 5], [$o, 9]]);   // 2, 3, 4
        $this->jobWithHistory($user, 40, [[$a, 0], [$s, 4], [$r, 6]]);            // 4, 2
        $this->jobWithHistory($user, 40, [[$a, 0], [$s, 6], [$i, 9]]);            // 6, 3 (Interview masih berjalan)
        $this->jobWithHistory($user, 40, [[$a, 0], [$g, 30]]);                    // Ghosted: dilewati
        $this->jobWithHistory($user, 40, [[$a, 0], [$i, 8], [$o, 10]]);           // 8, 2 (loncat Screening)

        $this->actingAs($user)
            ->getJson('/stats/B')
            ->assertJsonPath('charts.stage_duration.status', 'ok')
            ->assertJsonPath('charts.stage_duration.unit', 'hari')
            ->assertJsonPath('charts.stage_duration.labels', ['Applied', 'Screening', 'Interview'])
            ->assertJsonPath('charts.stage_duration.series.0.data', [5, 2.7, null]);
    }

    public function test_lama_tahap_data_belum_cukup(): void
    {
        $user = User::factory()->create();

        $this->jobWithHistory($user, 40, [[JobStatus::Applied, 0], [JobStatus::Screening, 3]]);

        $this->actingAs($user)
            ->getJson('/stats/B')
            ->assertJsonPath('charts.stage_duration.status', 'insufficient')
            ->assertJsonPath('charts.stage_duration.current', 1)
            ->assertJsonPath('charts.stage_duration.min_required', 3);
    }

    // ----- #8 durasi pencarian -----

    public function test_durasi_dari_awal_pencarian(): void
    {
        // 59 hari lalu sampai hari ini, inklusif = 60 hari
        $start = today()->subDays(59);
        $user = User::factory()->create(['job_search_started_at' => $start]);

        $this->actingAs($user)
            ->getJson('/stats/B')
            ->assertJsonPath('cards.search_duration.value', 60)
            ->assertJsonPath('cards.search_duration.unit', 'hari')
            ->assertJsonPath('cards.search_duration.note', 'Sejak '.$start->copy()->locale('id')->translatedFormat('j F Y'));
    }

    public function test_durasi_dari_apply_pertama_jika_awal_pencarian_kosong(): void
    {
        $user = User::factory()->create(['job_search_started_at' => null]);
        Job::factory()->for($user)->create(['applied_date' => today()->subDays(10)]);

        $this->actingAs($user)
            ->getJson('/stats/B')
            ->assertJsonPath('cards.search_duration.value', 11);
    }

    public function test_durasi_memakai_tanggal_yang_lebih_awal(): void
    {
        $user = User::factory()->create(['job_search_started_at' => today()->subDays(5)]);
        Job::factory()->for($user)->create(['applied_date' => today()->subDays(20)]);

        $this->actingAs($user)
            ->getJson('/stats/B')
            ->assertJsonPath('cards.search_duration.value', 21);
    }

    public function test_durasi_tanpa_data_sama_sekali(): void
    {
        $user = User::factory()->create(['job_search_started_at' => null]);

        $this->actingAs($user)
            ->getJson('/stats/B')
            ->assertJsonPath('cards.search_duration.status', 'insufficient')
            ->assertJsonPath('cards.search_duration.current', 0)
            ->assertJsonPath('cards.search_duration.min_required', 1);
    }

    // ----- #9 tren apply -----

    private function seedTrend(User $user): void
    {
        $monday = today()->startOfWeek();

        // 3 loker dua minggu lalu, 1 minggu lalu, 2 minggu ini
        foreach ([[14, 3], [7, 1], [0, 2]] as [$weeksBack, $count]) {
            Job::factory()->count($count)->for($user)->create([
                'applied_date' => $monday->copy()->subDays($weeksBack),
            ]);
        }
    }

    public function test_tren_mingguan(): void
    {
        $user = User::factory()->create();
        $this->seedTrend($user);

        $response = $this->actingAs($user)->getJson('/stats/B')
            ->assertJsonPath('charts.apply_weekly.type', 'line');

        $data = $response->json('charts.apply_weekly.series.0.data');
        $labels = $response->json('charts.apply_weekly.labels');

        $this->assertSame([3, 1, 2], array_slice($data, -3));
        $this->assertSame(6, array_sum($data));
        $this->assertCount(count($data), $labels);
        $this->assertSame(
            today()->startOfWeek()->locale('id')->translatedFormat('j M'),
            end($labels)
        );
    }

    public function test_tren_bulanan(): void
    {
        $user = User::factory()->create();
        $this->seedTrend($user);

        $response = $this->actingAs($user)->getJson('/stats/B');

        $data = $response->json('charts.apply_monthly.series.0.data');
        $labels = $response->json('charts.apply_monthly.labels');

        $this->assertSame(6, array_sum($data));
        $this->assertSame(
            today()->startOfMonth()->locale('id')->translatedFormat('M Y'),
            end($labels)
        );
    }

    public function test_tren_butuh_minimal_lima_loker(): void
    {
        $user = User::factory()->create();
        Job::factory()->count(3)->for($user)->create();

        $this->actingAs($user)
            ->getJson('/stats/B')
            ->assertJsonPath('charts.apply_weekly.status', 'insufficient')
            ->assertJsonPath('charts.apply_monthly.status', 'insufficient');
    }

    public function test_data_user_lain_tidak_ikut_terhitung(): void
    {
        $user = User::factory()->create(['job_search_started_at' => null]);
        $other = User::factory()->create();

        Job::factory()->count(6)->for($other)->create(['applied_date' => today()->subDays(30)]);

        $this->actingAs($user)
            ->getJson('/stats/B')
            ->assertJsonPath('sample', 0)
            ->assertJsonPath('cards.search_duration.status', 'insufficient')
            ->assertJsonPath('charts.apply_weekly.status', 'insufficient');
    }
}