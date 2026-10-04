<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function jobWithHistory(User $user, array $attributes = []): Job
    {
        $job = Job::factory()->for($user)->create($attributes);

        $job->statusHistory()->delete();
        $job->statusHistory()->create(['status' => 'applied', 'changed_at' => $job->applied_date->toDateString()]);

        return $job;
    }

    public function test_pemilik_bisa_menghapus_loker_dan_diarahkan_ke_list(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, ['company_name' => 'PT Contoh Maju', 'position' => 'Backend Developer']);

        $this->actingAs($user)
            ->delete(route('jobs.destroy', $job))
            ->assertRedirect(route('jobs.index'))
            ->assertSessionHas('success', 'Loker Backend Developer di PT Contoh Maju dihapus.');

        $this->assertDatabaseMissing('jobs', ['id' => $job->id]);
    }

    public function test_riwayat_status_ikut_terhapus(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user);

        $this->assertDatabaseHas('status_history', ['job_id' => $job->id]);

        $this->actingAs($user)->delete(route('jobs.destroy', $job));

        $this->assertDatabaseMissing('status_history', ['job_id' => $job->id]);
    }

    public function test_hanya_loker_yang_dipilih_yang_terhapus(): void
    {
        $user = User::factory()->create();
        $target = $this->jobWithHistory($user);
        $other = $this->jobWithHistory($user);

        $this->actingAs($user)->delete(route('jobs.destroy', $target));

        $this->assertDatabaseMissing('jobs', ['id' => $target->id]);
        $this->assertDatabaseHas('jobs', ['id' => $other->id]);
        $this->assertDatabaseHas('status_history', ['job_id' => $other->id]);
    }

    public function test_filter_dan_halaman_list_dibawa_pulang_setelah_hapus(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user);

        $listUrl = route('jobs.index', ['q' => 'backend', 'sort' => 'company_name', 'page' => 2]);

        // Datang dari list: alamat list disimpan di session
        $this->actingAs($user)->from($listUrl)->get(route('jobs.show', $job))->assertOk();

        $this->actingAs($user)
            ->delete(route('jobs.destroy', $job))
            ->assertRedirect($listUrl);
    }

    public function test_pengguna_lain_mendapat_404_dan_loker_tidak_terhapus(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $job = $this->jobWithHistory($owner);

        $this->actingAs($other)->delete(route('jobs.destroy', $job))->assertNotFound();

        $this->assertDatabaseHas('jobs', ['id' => $job->id]);
        $this->assertDatabaseHas('status_history', ['job_id' => $job->id]);
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $job = $this->jobWithHistory(User::factory()->create());

        $this->delete(route('jobs.destroy', $job))->assertRedirect(route('login'));

        $this->assertDatabaseHas('jobs', ['id' => $job->id]);
    }

    public function test_id_yang_tidak_ada_mendapat_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/jobs/999999')->assertNotFound();
    }

    public function test_tombol_hapus_dan_dialog_konfirmasi_tampil_untuk_pemilik(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user);

        $this->actingAs($user)->get(route('jobs.show', $job))
            ->assertSee('action="'.route('jobs.destroy', $job).'"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('Hapus loker ini?')
            ->assertSee('Ya, hapus');
    }
}