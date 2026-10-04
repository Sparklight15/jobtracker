<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JobShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Hari ini dibekukan supaya perhitungan lama tahap bisa dipastikan
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
            'current_status' => $history[array_key_last($history)][0],
        ]);

        $job->statusHistory()->delete();

        foreach ($history as [$status, $date]) {
            $job->statusHistory()->create(['status' => $status, 'changed_at' => $date]);
        }

        return $job;
    }

    // ---------------------------------------------------------------
    // Akses
    // ---------------------------------------------------------------

    public function test_tamu_diarahkan_ke_login(): void
    {
        $job = $this->jobWithHistory(User::factory()->create(), [['applied', '2026-09-01']]);

        $this->get(route('jobs.show', $job))->assertRedirect(route('login'));
    }

    public function test_pemilik_bisa_membuka_detail(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']], [
            'position' => 'Backend Developer',
            'company_name' => 'PT Contoh Maju',
        ]);

        $this->actingAs($user)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Backend Developer')
            ->assertSee('PT Contoh Maju');
    }

    public function test_pengguna_lain_mendapat_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $job = $this->jobWithHistory($owner, [['applied', '2026-09-01']]);

        $this->actingAs($other)->get(route('jobs.show', $job))->assertNotFound();
    }

    public function test_id_yang_tidak_ada_mendapat_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/jobs/999999')->assertNotFound();
    }

    public function test_id_bukan_angka_mendapat_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/jobs/abc')->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Isi halaman
    // ---------------------------------------------------------------

    public function test_atribut_tampil_dalam_kelompok(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $response = $this->actingAs($user)->get(route('jobs.show', $job))->assertOk();

        foreach (['riwayat', 'ubah-status', 'identitas', 'waktu', 'channel', 'lokasi', 'gaji', 'fit', 'hasil', 'skill', 'catatan'] as $group) {
            $response->assertSee('id="g-'.$group.'"', false);
        }
    }

    public function test_gaji_ditawarkan_hanya_tampil_saat_status_offer(): void
    {
        $user = User::factory()->create();
        $attributes = ['salary_min' => 5000000, 'salary_max' => 6000000, 'salary_offered' => 15000000];

        $offer = $this->jobWithHistory($user, [['applied', '2026-09-01'], ['offer', '2026-09-15']], $attributes);
        $applied = $this->jobWithHistory($user, [['applied', '2026-09-01']], $attributes);

        $this->actingAs($user)->get(route('jobs.show', $offer))->assertSee('Rp 15.000.000');
        $this->actingAs($user)->get(route('jobs.show', $applied))->assertDontSee('Rp 15.000.000');
    }

    public function test_alasan_penolakan_hanya_tampil_saat_status_rejected(): void
    {
        $user = User::factory()->create();

        $rejected = $this->jobWithHistory($user, [['applied', '2026-09-01'], ['rejected', '2026-09-10']], [
            'rejection_reason' => 'skill_gap',
        ]);
        $applied = $this->jobWithHistory($user, [['applied', '2026-09-01']], [
            'rejection_reason' => 'skill_gap',
        ]);

        $this->actingAs($user)->get(route('jobs.show', $rejected))
            ->assertSee('id="alasan-penolakan"', false);

        $this->actingAs($user)->get(route('jobs.show', $applied))
            ->assertDontSee('id="alasan-penolakan"', false);
    }

    public function test_skill_yang_kurang_ditampilkan(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $job->skillGaps()->forceCreate(['job_id' => $job->id, 'skill_name' => 'Kubernetes']);

        $this->actingAs($user)->get(route('jobs.show', $job))
            ->assertSee('Kubernetes')
            ->assertDontSee('Belum ada skill yang dicatat.');
    }

    public function test_link_lowongan_hanya_http_atau_https(): void
    {
        $user = User::factory()->create();

        $safe = $this->jobWithHistory($user, [['applied', '2026-09-01']], ['job_url' => 'https://contoh.co.id/loker/1']);
        $unsafe = $this->jobWithHistory($user, [['applied', '2026-09-01']], ['job_url' => 'javascript:alert(1)']);

        $this->actingAs($user)->get(route('jobs.show', $safe))
            ->assertSee('href="https://contoh.co.id/loker/1"', false);

        $this->actingAs($user)->get(route('jobs.show', $unsafe))
            ->assertDontSee('href="javascript:alert(1)"', false);
    }

    // ---------------------------------------------------------------
    // Tombol kembali
    // ---------------------------------------------------------------

    public function test_tombol_kembali_membawa_filter_dari_list(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $listUrl = route('jobs.index', ['q' => 'backend', 'sort' => 'company_name', 'page' => 2]);

        $this->actingAs($user)
            ->from($listUrl)
            ->get(route('jobs.show', $job))
            ->assertSee('href="'.e($listUrl).'"', false);
    }

    public function test_tombol_kembali_mengabaikan_asal_dari_situs_lain(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->actingAs($user)
            ->from('https://situs-lain.example/jobs?q=x')
            ->get(route('jobs.show', $job))
            ->assertDontSee('situs-lain.example')
            ->assertSee('href="'.route('jobs.index').'"', false);
    }

    public function test_tombol_kembali_tetap_membawa_filter_setelah_ubah_status(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $listUrl = route('jobs.index', ['q' => 'backend', 'page' => 2]);

        // Datang dari list: alamat list disimpan di session
        $this->actingAs($user)->from($listUrl)->get(route('jobs.show', $job))->assertOk();

        // Halaman detail dimuat ulang dari halaman detail itu sendiri (setelah ubah status)
        $this->actingAs($user)
            ->from(route('jobs.show', $job))
            ->get(route('jobs.show', $job))
            ->assertSee('href="'.e($listUrl).'"', false);
    }

    // ---------------------------------------------------------------
    // Timeline dan form
    // ---------------------------------------------------------------

    public function test_timeline_menampilkan_riwayat_berurutan_beserta_lama_tahap(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [
            ['applied', '2026-09-01'],
            ['screening', '2026-09-05'],
            ['interview', '2026-09-12'],
        ]);

        // Applied ke Screening 4 hari, Screening ke Interview 7 hari
        $this->actingAs($user)->get(route('jobs.show', $job))
            ->assertSee('4 hari')
            ->assertSee('7 hari');
    }

    public function test_status_final_tidak_menampilkan_lama_tahap_terakhir(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [
            ['applied', '2026-09-01'],
            ['rejected', '2026-09-03'],
        ]);

        // Applied ke Rejected 2 hari. Rejected sudah final: tidak dihitung sampai hari ini (17 hari)
        $this->actingAs($user)->get(route('jobs.show', $job))
            ->assertSee('2 hari')
            ->assertDontSee('17 hari');
    }

    public function test_riwayat_kosong_menampilkan_pesan(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);
        $job->statusHistory()->delete();

        $this->actingAs($user)->get(route('jobs.show', $job))
            ->assertSee('Belum ada riwayat status.');
    }

    public function test_form_ubah_status_tampil_untuk_pemilik(): void
    {
        $user = User::factory()->create();
        $job = $this->jobWithHistory($user, [['applied', '2026-09-01']]);

        $this->actingAs($user)->get(route('jobs.show', $job))
            ->assertSee('action="'.route('jobs.status', $job).'"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="changed_at"', false)
            ->assertSee('Simpan Status');
    }
}