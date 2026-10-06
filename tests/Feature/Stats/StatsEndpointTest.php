<?php

namespace Tests\Feature\Stats;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_tidak_bisa_mengakses_endpoint(): void
    {
        $this->getJson('/stats/A')->assertUnauthorized();
        $this->get('/stats/A')->assertRedirect(route('login'));
    }

    public function test_grup_tidak_dikenal_menghasilkan_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/stats/Z')
            ->assertNotFound();
    }

    public function test_grup_a_mengembalikan_amplop_sesuai_kontrak(): void
    {
        $user = User::factory()->create();
        Job::factory()->for($user)->create();

        $this->actingAs($user)
            ->getJson('/stats/A')
            ->assertOk()
            ->assertJsonStructure(['group', 'period', 'sample', 'cards', 'charts'])
            ->assertJsonPath('group', 'A')
            ->assertJsonPath('sample', 1)
            ->assertJsonPath('cards.total_apply.status', 'ok')
            ->assertJsonPath('cards.total_apply.value', 1)
            ->assertJsonPath('charts.per_stage.status', 'insufficient');
    }

    public function test_huruf_grup_tidak_peka_kapital(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/stats/a')
            ->assertOk()
            ->assertJsonPath('group', 'A');
    }

    public function test_periode_tidak_valid_jatuh_ke_bawaan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/stats/A?period=30d')->assertJsonPath('period', '30d');
        $this->actingAs($user)->getJson('/stats/A?period=ngawur')->assertJsonPath('period', '90d');
    }

    public function test_periode_memfilter_cohort_berdasarkan_tanggal_apply(): void
    {
        $user = User::factory()->create();

        Job::factory()->for($user)->create(['applied_date' => today()]);
        Job::factory()->for($user)->create(['applied_date' => today()->subDays(100)]);

        $this->actingAs($user);

        $this->getJson('/stats/A?period=90d')->assertJsonPath('sample', 1);
        $this->getJson('/stats/A?period=all')->assertJsonPath('sample', 2);
    }

    public function test_data_user_lain_tidak_ikut_terhitung(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Job::factory()->for($user)->create();
        Job::factory()->count(3)->for($other)->create();

        $this->actingAs($user)
            ->getJson('/stats/A')
            ->assertJsonPath('sample', 1);
    }

    public function test_halaman_beranda_menampilkan_semua_section(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create())
            ->get('/beranda')
            ->assertOk()
            ->assertSee('Funnel lamaran')
            ->assertSee('Prediksi dan tren');
    }
}