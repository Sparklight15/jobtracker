<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JobStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Hari ini dibekukan supaya validasi tanggal dan perhitungan bisa dipastikan
        $this->travelTo(Carbon::parse('2026-09-20 10:00:00'));
    }

    /**
     * Buat loker dengan riwayat status tertentu. Status saat ini = status baris terakhir.
     *
     * @param  array<int, array{0: string, 1: string}>  $history  [[status, tanggal], ...]
     * @param  array<string, mixed>  $attributes
     */
    private function jobWithHistory(User $user, array $history, array $attributes = []): Job
    {
        $job = Job::factory()->for($user)->create($attributes + [
            'applied_date' => $history[0][1],
            'first_response_date' => null,
            'current_status' => $history[array_key_last($history)][0],
        ]);

        $job->statusHistory()->delete();

        foreach ($history as [$status, $date]) {
            $job->statusHistory()->create(['status' => $status, 'changed_at' => $date]);
        }

        return $job;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function changeStatus(User $user, Job $job, array $data): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('jobs.show', $job))
            ->patch(route('jobs.status', $job), $data);
    }

    // ---------------------------------------------------------------
    // Alur utama
    // ---------------------------------------------------------------

    public function test_ubah_status_menambah_riwayat_dan_memperbarui_status_saat_ini(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->changeStatus($user, $job, ['status' => 'screening', 'changed_at' => '2026-09-05'])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', 'Status diubah menjadi Screening.');

        $this->assertSame('screening', $job->fresh()->current_status->value);
        $this->assertSame(2, $job->statusHistory()->count());
        $this->assertDatabaseHas('status_history', [
            'job_id' => $job->id,
            'status' => 'screening',
            'changed_at' => '2026-09-05',
        ]);
    }

    public function test_pesan_sukses_tampil_di_halaman_detail(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->actingAs($user)
            ->followingRedirects()
            ->patch(route('jobs.status', $job), ['status' => 'screening', 'changed_at' => '2026-09-05'])
            ->assertOk()
            ->assertSee('Status diubah menjadi Screening.');
    }

    public function test_status_saat_ini_mengikuti_tanggal_terbaru_bukan_urutan_input(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [
            ['applied', '2026-09-01'],
            ['interview', '2026-09-12'],
        ]);

        // Data lama diinput belakangan: tanggalnya lebih awal dari status saat ini
        $this->changeStatus($user, $job, ['status' => 'screening', 'changed_at' => '2026-09-05'])
            ->assertSessionHasNoErrors();

        $this->assertSame('interview', $job->fresh()->current_status->value);
        $this->assertSame(3, $job->statusHistory()->count());
    }

    public function test_ghosted_bisa_dipilih_manual(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->changeStatus($user, $job, ['status' => 'ghosted', 'changed_at' => '2026-09-18'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ghosted', $job->fresh()->current_status->value);
        $this->assertDatabaseHas('status_history', [
            'job_id' => $job->id,
            'status' => 'ghosted',
            'changed_at' => '2026-09-18',
        ]);
    }

    // ---------------------------------------------------------------
    // Respons pertama
    // ---------------------------------------------------------------

    public function test_respons_pertama_terisi_saat_status_pertama_berubah(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->changeStatus($user, $job, ['status' => 'screening', 'changed_at' => '2026-09-05']);

        $this->assertSame('2026-09-05', $job->fresh()->first_response_date->toDateString());
    }

    public function test_ghosted_tidak_dihitung_sebagai_respons_pertama(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->changeStatus($user, $job, ['status' => 'ghosted', 'changed_at' => '2026-09-18']);

        $this->assertNull($job->fresh()->first_response_date);
    }

    public function test_respons_pertama_yang_sudah_ada_tidak_ditimpa(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']], [
            'first_response_date' => '2026-09-03',
        ]);

        $this->changeStatus($user, $job, ['status' => 'screening', 'changed_at' => '2026-09-05']);

        $this->assertSame('2026-09-03', $job->fresh()->first_response_date->toDateString());
    }

    // ---------------------------------------------------------------
    // Data hasil (Rejected dan Offer)
    // ---------------------------------------------------------------

    public function test_alasan_penolakan_tersimpan_saat_rejected(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']], ['rejection_reason' => null]);

        $this->changeStatus($user, $job, [
            'status' => 'rejected',
            'changed_at' => '2026-09-10',
            'rejection_reason' => 'skill_gap',
        ])->assertSessionHasNoErrors();

        $this->assertSame('skill_gap', $job->fresh()->rejection_reason->value);
    }

    public function test_keputusan_dan_gaji_offer_tersimpan_saat_offer(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']], [
            'offer_decision' => null,
            'salary_offered' => null,
        ]);

        $this->changeStatus($user, $job, [
            'status' => 'offer',
            'changed_at' => '2026-09-15',
            'offer_decision' => 'pending',
            'salary_offered' => '15.000.000',
        ])->assertSessionHasNoErrors();

        $fresh = $job->fresh();
        $this->assertSame('pending', $fresh->offer_decision->value);
        $this->assertSame(15000000, $fresh->salary_offered);
    }

    public function test_alasan_penolakan_diabaikan_jika_status_bukan_rejected(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']], ['rejection_reason' => null]);

        $this->changeStatus($user, $job, [
            'status' => 'screening',
            'changed_at' => '2026-09-05',
            'rejection_reason' => 'skill_gap',
        ]);

        $this->assertNull($job->fresh()->rejection_reason);
    }

    // ---------------------------------------------------------------
    // Validasi
    // ---------------------------------------------------------------

    public function test_status_sama_dengan_status_terakhir_ditolak(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [
            ['applied', '2026-09-01'],
            ['screening', '2026-09-05'],
        ]);

        $this->changeStatus($user, $job, ['status' => 'screening', 'changed_at' => '2026-09-10'])
            ->assertSessionHasErrors('status');

        $this->assertSame(2, $job->statusHistory()->count());
    }

    public function test_status_sama_dengan_status_tepat_sesudahnya_ditolak(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [
            ['applied', '2026-09-01'],
            ['interview', '2026-09-12'],
        ]);

        // Interview sudah tercatat tanggal 12; menyisipkannya tanggal 5 berarti dobel berurutan
        $this->changeStatus($user, $job, ['status' => 'interview', 'changed_at' => '2026-09-05'])
            ->assertSessionHasErrors('status');

        $this->assertSame(2, $job->statusHistory()->count());
    }

    public function test_tanggal_sebelum_tanggal_apply_ditolak(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-10']]);

        $this->changeStatus($user, $job, ['status' => 'screening', 'changed_at' => '2026-09-05'])
            ->assertSessionHasErrors('changed_at');

        $this->assertSame(1, $job->statusHistory()->count());
    }

    public function test_tanggal_masa_depan_ditolak(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->changeStatus($user, $job, ['status' => 'screening', 'changed_at' => '2026-09-21'])
            ->assertSessionHasErrors('changed_at');

        $this->assertSame(1, $job->statusHistory()->count());
    }

    public function test_tanggal_hari_ini_diterima(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->changeStatus($user, $job, ['status' => 'screening', 'changed_at' => '2026-09-20'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $job->statusHistory()->count());
    }

    public function test_status_tidak_valid_ditolak(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->changeStatus($user, $job, ['status' => 'hired', 'changed_at' => '2026-09-05'])
            ->assertSessionHasErrors('status');

        $this->changeStatus($user, $job, ['changed_at' => '2026-09-05'])
            ->assertSessionHasErrors('status');
    }

    public function test_gaji_ditawarkan_harus_angka(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->changeStatus($user, $job, [
            'status' => 'offer',
            'changed_at' => '2026-09-15',
            'salary_offered' => 'sepuluh juta',
        ])->assertSessionHasErrors('salary_offered');

        $this->assertSame(1, $job->statusHistory()->count());
    }

    // ---------------------------------------------------------------
    // Otorisasi
    // ---------------------------------------------------------------

    public function test_pengguna_lain_mendapat_404_dan_data_tidak_berubah(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $job = $this->jobWithHistory($owner, [['applied', '2026-09-01']]);

        $this->changeStatus($other, $job, ['status' => 'screening', 'changed_at' => '2026-09-05'])
            ->assertNotFound();

        $this->assertSame('applied', $job->fresh()->current_status->value);
        $this->assertSame(1, $job->statusHistory()->count());
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $job = $this->jobWithHistory(User::factory()->create(), [['applied', '2026-09-01']]);

        $this->patch(route('jobs.status', $job), ['status' => 'screening', 'changed_at' => '2026-09-05'])
            ->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------
    // Transaksi
    // ---------------------------------------------------------------

    public function test_riwayat_dibatalkan_jika_update_loker_gagal(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        // Paksa update tabel jobs gagal setelah baris riwayat sempat ditambahkan
        Job::updating(function () {
            throw new \RuntimeException('Gagal sengaja untuk uji transaksi');
        });

        try {
            $this->changeStatus($user, $job, ['status' => 'screening', 'changed_at' => '2026-09-05'])
                ->assertStatus(500);
        } finally {
            Job::flushEventListeners();
        }

        $this->assertSame(1, $job->statusHistory()->count());
        $this->assertSame('applied', $job->fresh()->current_status->value);
    }
}