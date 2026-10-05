<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JobCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Hari ini dibekukan supaya validasi tanggal bisa dipastikan
        $this->travelTo(Carbon::parse('2026-09-20 10:00:00'));
    }

    /**
     * Data minimal: hanya kolom wajib.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'PT Contoh Maju',
            'position' => 'Backend Developer',
            'applied_date' => '2026-09-01',
            'channel' => 'linkedin',
            'status' => 'applied',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function store(User $user, array $data): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('jobs.create'))
            ->post(route('jobs.store'), $data);
    }

    // ---------------------------------------------------------------
    // Akses dan tampilan form
    // ---------------------------------------------------------------

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('jobs.create'))->assertRedirect(route('login'));
        $this->post(route('jobs.store'), $this->payload())->assertRedirect(route('login'));

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_form_tampil_dengan_semua_kelompok_dan_tanda_wajib(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('jobs.create'))->assertOk();

        $response
            ->assertSee('action="'.route('jobs.store').'"', false)
            ->assertSee('name="company_name"', false)
            ->assertSee('name="skills[]"', false)
            ->assertSee('(wajib)');

        foreach (['identitas', 'waktu', 'channel', 'lokasi', 'gaji', 'fit', 'skill', 'catatan'] as $group) {
            $response->assertSee('id="g-'.$group.'"', false);
        }
    }

    public function test_dropdown_berisi_pilihan_dari_enum(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('jobs.create'))
            ->assertSee('<option value="job_portal"', false)
            ->assertSee('<option value="ghosted"', false)
            ->assertSee('<option value="hybrid"', false)
            ->assertSee('<option value="Teknologi"', false)
            ->assertSee('<option value="tailored"', false);
    }

    public function test_menu_tambah_loker_mengarah_ke_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('jobs.index'))
            ->assertSee('href="'.route('jobs.create').'"', false);
    }

    public function test_isian_kembali_saat_validasi_gagal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('jobs.create'))
            ->followingRedirects()
            ->post(route('jobs.store'), $this->payload(['position' => '', 'company_name' => 'PT Isian Lama']))
            ->assertOk()
            ->assertSee('PT Isian Lama')
            ->assertSee('Posisi wajib diisi.');
    }

    // ---------------------------------------------------------------
    // Simpan
    // ---------------------------------------------------------------

    public function test_simpan_minimal_dengan_kolom_wajib_saja(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload())->assertSessionHasNoErrors();

        $job = Job::query()->firstOrFail();

        $this->assertSame($user->id, $job->user_id);
        $this->assertSame('applied', $job->current_status->value);
        $this->assertNull($job->first_response_date);
        $this->assertSame(1, $job->statusHistory()->count());
        $this->assertDatabaseHas('status_history', [
            'job_id' => $job->id,
            'status' => 'applied',
            'changed_at' => '2026-09-01',
        ]);
    }

    public function test_setelah_simpan_diarahkan_ke_detail_dengan_pesan_sukses(): void
    {
        $user = User::factory()->create();

        $response = $this->store($user, $this->payload());

        $job = Job::query()->firstOrFail();

        $response
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', 'Loker Backend Developer di PT Contoh Maju ditambahkan.');
    }

    public function test_pesan_sukses_tampil_di_halaman_detail(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('jobs.create'))
            ->followingRedirects()
            ->post(route('jobs.store'), $this->payload())
            ->assertOk()
            ->assertSee('Loker Backend Developer di PT Contoh Maju ditambahkan.');
    }

    public function test_simpan_lengkap(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload([
            'job_url' => 'https://contoh.co.id/loker/1',
            'industry_sector' => 'Teknologi',
            'channel' => 'referral',
            'has_referral' => '1',
            'referrer_name' => 'Budi',
            'city' => 'Bandung',
            'work_mode' => 'hybrid',
            'salary_min' => '9.500.000',
            'salary_max' => '17.000.000',
            'fit_score' => '4',
            'skill_match_score' => '3',
            'cv_customization' => 'tailored',
            'notes' => 'Proses seleksi lewat email.',
        ]))->assertSessionHasNoErrors();

        $job = Job::query()->firstOrFail();

        $this->assertSame($user->id, $job->user_id);
        $this->assertSame('https://contoh.co.id/loker/1', $job->job_url);
        $this->assertSame('Teknologi', $job->industry_sector->value);
        $this->assertSame('referral', $job->channel->value);
        $this->assertTrue($job->has_referral);
        $this->assertSame('Budi', $job->referrer_name);
        $this->assertSame('Bandung', $job->city);
        $this->assertSame('hybrid', $job->work_mode->value);
        $this->assertSame(9500000, $job->salary_min);
        $this->assertSame(17000000, $job->salary_max);
        $this->assertSame(4, $job->fit_score);
        $this->assertSame(3, $job->skill_match_score);
        $this->assertSame('tailored', $job->cv_customization->value);
        $this->assertSame('Proses seleksi lewat email.', $job->notes);
    }

    public function test_user_id_tidak_bisa_dipalsukan(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->store($user, $this->payload(['user_id' => $other->id]))->assertSessionHasNoErrors();

        $this->assertSame($user->id, Job::query()->firstOrFail()->user_id);
    }

    // ---------------------------------------------------------------
    // Status awal selain Applied
    // ---------------------------------------------------------------

    public function test_status_selain_applied_membuat_dua_baris_riwayat_dan_respons_pertama(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['status' => 'screening', 'changed_at' => '2026-09-05']))
            ->assertSessionHasNoErrors();

        $job = Job::query()->firstOrFail();

        $this->assertSame('screening', $job->current_status->value);
        $this->assertSame(2, $job->statusHistory()->count());
        $this->assertSame('2026-09-05', $job->first_response_date->toDateString());
        $this->assertDatabaseHas('status_history', ['job_id' => $job->id, 'status' => 'applied', 'changed_at' => '2026-09-01']);
        $this->assertDatabaseHas('status_history', ['job_id' => $job->id, 'status' => 'screening', 'changed_at' => '2026-09-05']);
    }

    public function test_ghosted_tidak_dihitung_sebagai_respons_pertama(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['status' => 'ghosted', 'changed_at' => '2026-09-18']))
            ->assertSessionHasNoErrors();

        $job = Job::query()->firstOrFail();

        $this->assertSame('ghosted', $job->current_status->value);
        $this->assertNull($job->first_response_date);
    }

    public function test_alasan_penolakan_tersimpan_saat_rejected(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload([
            'status' => 'rejected',
            'changed_at' => '2026-09-10',
            'rejection_reason' => 'skill_gap',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('skill_gap', Job::query()->firstOrFail()->rejection_reason->value);
    }

    public function test_keputusan_dan_gaji_offer_tersimpan_saat_offer(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload([
            'status' => 'offer',
            'changed_at' => '2026-09-15',
            'offer_decision' => 'pending',
            'salary_offered' => '15.000.000',
        ]))->assertSessionHasNoErrors();

        $job = Job::query()->firstOrFail();

        $this->assertSame('pending', $job->offer_decision->value);
        $this->assertSame(15000000, $job->salary_offered);
    }

    // ---------------------------------------------------------------
    // Validasi bersyarat
    // ---------------------------------------------------------------

    public function test_isian_hasil_diabaikan_jika_status_tidak_cocok(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload([
            'status' => 'applied',
            'rejection_reason' => 'skill_gap',
            'offer_decision' => 'accepted',
            'salary_offered' => '15.000.000',
            'changed_at' => '2026-09-05',
        ]))->assertSessionHasNoErrors();

        $job = Job::query()->firstOrFail();

        $this->assertNull($job->rejection_reason);
        $this->assertNull($job->offer_decision);
        $this->assertNull($job->salary_offered);
        $this->assertSame(1, $job->statusHistory()->count());
    }

    public function test_nama_referrer_diabaikan_jika_tanpa_referral(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['has_referral' => '0', 'referrer_name' => 'Budi']))
            ->assertSessionHasNoErrors();

        $job = Job::query()->firstOrFail();

        $this->assertFalse($job->has_referral);
        $this->assertNull($job->referrer_name);
    }

    public function test_gaji_maksimum_kurang_dari_minimum_ditolak(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['salary_min' => '10.000.000', 'salary_max' => '5.000.000']))
            ->assertSessionHasErrors('salary_max');

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_gaji_maksimum_sama_dengan_minimum_diterima(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['salary_min' => '10000000', 'salary_max' => '10000000']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_hanya_salah_satu_gaji_diisi_diterima(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['salary_min' => '8000000']))->assertSessionHasNoErrors();
        $this->store($user, $this->payload(['salary_max' => '12000000']))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('jobs', 2);
    }

    // ---------------------------------------------------------------
    // Validasi dasar
    // ---------------------------------------------------------------

    public function test_kolom_wajib_harus_diisi(): void
    {
        $user = User::factory()->create();

        $this->store($user, [])
            ->assertSessionHasErrors(['company_name', 'position', 'applied_date', 'channel', 'status']);

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_nilai_enum_tidak_valid_ditolak(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload([
            'channel' => 'merpati',
            'status' => 'hired',
            'industry_sector' => 'Perkebunan',
            'work_mode' => 'anywhere',
            'cv_customization' => 'rapi',
        ]))->assertSessionHasErrors(['channel', 'status', 'industry_sector', 'work_mode', 'cv_customization']);

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_panjang_teks_dibatasi(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload([
            'company_name' => str_repeat('a', 151),
            'position' => str_repeat('a', 151),
            'city' => str_repeat('a', 101),
        ]))->assertSessionHasErrors(['company_name', 'position', 'city']);
    }

    public function test_tanggal_apply_masa_depan_ditolak(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['applied_date' => '2026-09-21']))
            ->assertSessionHasErrors('applied_date');

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_tanggal_status_berubah_wajib_untuk_status_selain_applied(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['status' => 'screening']))
            ->assertSessionHasErrors('changed_at');

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_tanggal_status_berubah_sebelum_tanggal_apply_ditolak(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload([
            'applied_date' => '2026-09-10',
            'status' => 'screening',
            'changed_at' => '2026-09-05',
        ]))->assertSessionHasErrors('changed_at');
    }

    public function test_tanggal_status_berubah_di_masa_depan_ditolak(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['status' => 'screening', 'changed_at' => '2026-09-21']))
            ->assertSessionHasErrors('changed_at');
    }

    public function test_gaji_harus_angka(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['salary_min' => 'sepuluh juta']))
            ->assertSessionHasErrors('salary_min');
    }

    public function test_skor_di_luar_satu_sampai_lima_ditolak(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['fit_score' => '6', 'skill_match_score' => '0']))
            ->assertSessionHasErrors(['fit_score', 'skill_match_score']);
    }

    public function test_link_lowongan_hanya_http_atau_https(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['job_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('job_url');

        $this->assertDatabaseCount('jobs', 0);
    }

    // ---------------------------------------------------------------
    // Skill yang kurang
    // ---------------------------------------------------------------

    public function test_skill_tersimpan_tanpa_duplikat_dan_tanpa_yang_kosong(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload([
            'skills' => ['Docker', ' docker ', 'Kubernetes', '', 'DOCKER'],
        ]))->assertSessionHasNoErrors();

        $job = Job::query()->firstOrFail();

        $this->assertSame(
            ['Docker', 'Kubernetes'],
            $job->skillGaps()->orderBy('skill_name')->pluck('skill_name')->all()
        );
    }

    public function test_tanpa_skill_tidak_membuat_baris_skill(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload())->assertSessionHasNoErrors();

        $this->assertDatabaseCount('job_skill_gaps', 0);
    }

    public function test_jumlah_skill_dibatasi_dua_puluh(): void
    {
        $user = User::factory()->create();

        $skills = array_map(fn (int $i) => "Skill {$i}", range(1, 21));

        $this->store($user, $this->payload(['skills' => $skills]))
            ->assertSessionHasErrors('skills');

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_nama_skill_dibatasi_seratus_karakter(): void
    {
        $user = User::factory()->create();

        $this->store($user, $this->payload(['skills' => [str_repeat('a', 101)]]))
            ->assertSessionHasErrors('skills.0');
    }

    // ---------------------------------------------------------------
    // Transaksi
    // ---------------------------------------------------------------

    public function test_semua_dibatalkan_jika_penyimpanan_gagal(): void
    {
        $user = User::factory()->create();

        // Paksa update tabel jobs gagal setelah loker, riwayat, dan skill sempat ditambahkan
        Job::updating(function () {
            throw new \RuntimeException('Gagal sengaja untuk uji transaksi');
        });

        try {
            $this->store($user, $this->payload([
                'status' => 'screening',
                'changed_at' => '2026-09-05',
                'skills' => ['Docker'],
            ]))->assertStatus(500);
        } finally {
            Job::flushEventListeners();
        }

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('status_history', 0);
        $this->assertDatabaseCount('job_skill_gaps', 0);
    }
}