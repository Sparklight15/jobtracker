<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Enums\RejectionReason;
use App\Models\Job;
use App\Models\StatusHistory;
use App\Models\User;
use App\Support\JobCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JobEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Hari ini dibekukan supaya validasi tanggal bisa dipastikan
        $this->travelTo(Carbon::parse('2026-09-20 10:00:00'));
    }

    /**
     * Data minimal: hanya kolom wajib. Form edit memakai semantik PUT, jadi kolom opsional
     * yang tidak dikirim berarti dikosongkan.
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
     * Loker yang sudah ada, dibuat lewat JobCreator supaya riwayatnya konsisten.
     * JobCreator dipanggil tanpa normalisasi request, jadi angka ditulis polos ("15000000").
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeJob(User $user, array $overrides = []): Job
    {
        return JobCreator::create($user, $this->payload($overrides));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function update(User $user, Job $job, array $data): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('jobs.edit', $job))
            ->put(route('jobs.update', $job), $data);
    }

    // ---------------------------------------------------------------
    // Akses dan kepemilikan
    // ---------------------------------------------------------------

    public function test_tamu_diarahkan_ke_login(): void
    {
        $job = $this->makeJob(User::factory()->create());

        $this->get(route('jobs.edit', $job))->assertRedirect(route('login'));
        $this->put(route('jobs.update', $job), $this->payload())->assertRedirect(route('login'));
    }

    public function test_bukan_pemilik_mendapat_404_di_halaman_edit_dan_update(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $job = $this->makeJob($owner, ['notes' => 'Asli']);

        $this->actingAs($intruder)->get(route('jobs.edit', $job))->assertNotFound();

        $this->update($intruder, $job, $this->payload(['notes' => 'Dibajak']))->assertNotFound();

        $this->assertSame('Asli', $job->fresh()->notes);
    }

    public function test_bukan_pemilik_mendapat_404_sebelum_validasi(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $job = $this->makeJob($owner);

        // Payload kosong: kalau validasi jalan lebih dulu, hasilnya redirect dengan error, bukan 404
        $this->update($intruder, $job, [])->assertNotFound();
    }

    public function test_loker_yang_tidak_ada_mendapat_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('jobs.edit', 9999))->assertNotFound();
        $this->actingAs($user)->put(route('jobs.update', 9999), $this->payload())->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Tampilan form dan isi otomatis
    // ---------------------------------------------------------------

    public function test_halaman_detail_punya_tombol_edit(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->actingAs($user)->get(route('jobs.show', $job))
            ->assertSee('href="'.route('jobs.edit', $job).'"', false);
    }

    public function test_form_edit_terisi_data_lama(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, [
            'job_url' => 'https://contoh.co.id/loker/1',
            'industry_sector' => 'Teknologi',
            'channel' => 'referral',
            'has_referral' => '1',
            'referrer_name' => 'Budi',
            'city' => 'Bandung',
            'work_mode' => 'hybrid',
            'salary_min' => '9500000',
            'fit_score' => '4',
            'cv_customization' => 'tailored',
            'notes' => 'Catatan lama.',
            'skills' => ['Docker', 'Kubernetes'],
        ]);

        $this->actingAs($user)->get(route('jobs.edit', $job))
            ->assertOk()
            ->assertSee('action="'.route('jobs.update', $job).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="PT Contoh Maju"', false)
            ->assertSee('value="Backend Developer"', false)
            ->assertSee('value="https://contoh.co.id/loker/1"', false)
            ->assertSee('value="2026-09-01"', false)
            ->assertSee('value="Bandung"', false)
            ->assertSee('value="Budi"', false)
            ->assertSee('value="9500000"', false)
            ->assertSee('<option value="Teknologi" selected', false)
            ->assertSee('<option value="referral" selected', false)
            ->assertSee('<option value="hybrid" selected', false)
            ->assertSee('<option value="4" selected', false)
            ->assertSee('<option value="tailored" selected', false)
            ->assertSee('Catatan lama.')
            ->assertSee('Docker')
            ->assertSee('Kubernetes')
            ->assertSee('Kembali ke Detail Loker');
    }

    public function test_status_saat_ini_terpilih_di_form_edit(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['status' => 'screening', 'changed_at' => '2026-09-05']);

        $this->actingAs($user)->get(route('jobs.edit', $job))
            ->assertSee('<option value="screening" selected', false);
    }

    public function test_hasil_loker_rejected_terisi_di_form_edit(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, [
            'status' => 'rejected',
            'changed_at' => '2026-09-10',
            'rejection_reason' => 'skill_gap',
        ]);

        $this->actingAs($user)->get(route('jobs.edit', $job))
            ->assertSee('<option value="skill_gap" selected', false);
    }

    public function test_isian_baru_kembali_saat_validasi_gagal(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->actingAs($user)
            ->from(route('jobs.edit', $job))
            ->followingRedirects()
            ->put(route('jobs.update', $job), $this->payload(['position' => '', 'company_name' => 'PT Isian Baru']))
            ->assertOk()
            ->assertSee('PT Isian Baru')
            ->assertSee('Posisi wajib diisi.');
    }

    public function test_skill_yang_dihapus_semua_tidak_muncul_lagi_saat_validasi_gagal(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['skills' => ['SkillUnikXyz']]);

        // Semua skill dihapus di form (tidak ada skills[]), tapi posisi dikosongkan sehingga gagal
        $this->actingAs($user)
            ->from(route('jobs.edit', $job))
            ->followingRedirects()
            ->put(route('jobs.update', $job), $this->payload(['position' => '']))
            ->assertOk()
            ->assertDontSee('SkillUnikXyz');

        $this->assertDatabaseHas('job_skill_gaps', ['job_id' => $job->id, 'skill_name' => 'SkillUnikXyz']);
    }

    // ---------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------

    public function test_update_mengubah_semua_atribut(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['city' => 'Jakarta']);

        $this->update($user, $job, $this->payload([
            'company_name' => 'PT Baru Sejahtera',
            'position' => 'Fullstack Developer',
            'job_url' => 'https://baru.co.id/1',
            'industry_sector' => 'Teknologi',
            'channel' => 'referral',
            'has_referral' => '1',
            'referrer_name' => 'Sari',
            'city' => 'Bandung',
            'work_mode' => 'hybrid',
            'salary_min' => '9.500.000',
            'salary_max' => '17.000.000',
            'fit_score' => '4',
            'skill_match_score' => '3',
            'cv_customization' => 'tailored',
            'notes' => 'Catatan baru.',
        ]))->assertSessionHasNoErrors();

        $job = $job->fresh();

        $this->assertSame($user->id, $job->user_id);
        $this->assertSame('PT Baru Sejahtera', $job->company_name);
        $this->assertSame('Fullstack Developer', $job->position);
        $this->assertSame('https://baru.co.id/1', $job->job_url);
        $this->assertSame('Teknologi', $job->industry_sector->value);
        $this->assertSame('referral', $job->channel->value);
        $this->assertTrue($job->has_referral);
        $this->assertSame('Sari', $job->referrer_name);
        $this->assertSame('Bandung', $job->city);
        $this->assertSame('hybrid', $job->work_mode->value);
        $this->assertSame(9500000, $job->salary_min);
        $this->assertSame(17000000, $job->salary_max);
        $this->assertSame(4, $job->fit_score);
        $this->assertSame(3, $job->skill_match_score);
        $this->assertSame('tailored', $job->cv_customization->value);
        $this->assertSame('Catatan baru.', $job->notes);
    }

    public function test_setelah_update_diarahkan_ke_detail_dengan_pesan_sukses(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload(['notes' => 'Baru']))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', 'Loker Backend Developer di PT Contoh Maju diperbarui.');
    }

    public function test_pesan_sukses_tampil_di_halaman_detail(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->actingAs($user)
            ->from(route('jobs.edit', $job))
            ->followingRedirects()
            ->put(route('jobs.update', $job), $this->payload(['notes' => 'Baru']))
            ->assertOk()
            ->assertSee('Loker Backend Developer di PT Contoh Maju diperbarui.');
    }

    public function test_kolom_opsional_yang_tidak_dikirim_dikosongkan(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['city' => 'Bandung', 'notes' => 'Lama', 'fit_score' => '5']);

        $this->update($user, $job, $this->payload())->assertSessionHasNoErrors();

        $job = $job->fresh();

        $this->assertNull($job->city);
        $this->assertNull($job->notes);
        $this->assertNull($job->fit_score);
    }

    public function test_user_id_tidak_bisa_dipalsukan(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload(['user_id' => $other->id]))->assertSessionHasNoErrors();

        $this->assertSame($user->id, $job->fresh()->user_id);
    }

    public function test_status_dan_respons_pertama_tidak_bisa_diubah_langsung(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload([
            'current_status' => 'offer',
            'first_response_date' => '2026-09-02',
        ]))->assertSessionHasNoErrors();

        $job = $job->fresh();

        $this->assertSame('applied', $job->current_status->value);
        $this->assertNull($job->first_response_date);
    }

    // ---------------------------------------------------------------
    // Validasi
    // ---------------------------------------------------------------

    public function test_kolom_wajib_harus_diisi(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, [])
            ->assertSessionHasErrors(['company_name', 'position', 'applied_date', 'channel', 'status']);
    }

    public function test_gaji_maksimum_kurang_dari_minimum_ditolak(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload(['salary_min' => '10.000.000', 'salary_max' => '5.000.000']))
            ->assertSessionHasErrors('salary_max');
    }

    public function test_link_lowongan_hanya_http_atau_https(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload(['job_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('job_url');
    }

    public function test_tanggal_apply_masa_depan_ditolak(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload(['applied_date' => '2026-09-21']))
            ->assertSessionHasErrors('applied_date');
    }

    // ---------------------------------------------------------------
    // Perubahan status dan riwayat
    // ---------------------------------------------------------------

    public function test_status_tetap_tidak_menambah_baris_riwayat(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload(['notes' => 'Hanya catatan']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $job->statusHistory()->count());
        $this->assertSame('applied', $job->fresh()->current_status->value);
    }

    public function test_status_selain_applied_yang_tidak_berubah_tidak_butuh_tanggal_dan_tidak_menambah_riwayat(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['status' => 'screening', 'changed_at' => '2026-09-05']);

        $this->update($user, $job, $this->payload(['status' => 'screening', 'notes' => 'Catatan']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $job->statusHistory()->count());
        $this->assertSame('screening', $job->fresh()->current_status->value);
        $this->assertSame('Catatan', $job->fresh()->notes);
    }

    public function test_status_berubah_menambah_baris_riwayat_dan_respons_pertama(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload(['status' => 'screening', 'changed_at' => '2026-09-05']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas(
                'success',
                'Loker Backend Developer di PT Contoh Maju diperbarui. Status diubah menjadi '.JobStatus::from('screening')->label().'.'
            );

        $job = $job->fresh();

        $this->assertSame('screening', $job->current_status->value);
        $this->assertSame(2, $job->statusHistory()->count());
        $this->assertSame('2026-09-05', $job->first_response_date->toDateString());
        $this->assertDatabaseHas('status_history', ['job_id' => $job->id, 'status' => 'applied', 'changed_at' => '2026-09-01']);
        $this->assertDatabaseHas('status_history', ['job_id' => $job->id, 'status' => 'screening', 'changed_at' => '2026-09-05']);
    }

    public function test_tanggal_perubahan_wajib_saat_status_berubah(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload(['status' => 'screening']))
            ->assertSessionHasErrors('changed_at');

        $this->assertSame(1, $job->statusHistory()->count());
        $this->assertSame('applied', $job->fresh()->current_status->value);
    }

    public function test_kembali_ke_applied_dari_status_lain_menambah_riwayat(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['status' => 'screening', 'changed_at' => '2026-09-05']);

        $this->update($user, $job, $this->payload(['status' => 'applied', 'changed_at' => '2026-09-10']))
            ->assertSessionHasNoErrors();

        $job = $job->fresh();

        $this->assertSame('applied', $job->current_status->value);
        $this->assertSame(3, $job->statusHistory()->count());
        // Respons pertama tidak ditimpa
        $this->assertSame('2026-09-05', $job->first_response_date->toDateString());
    }

    public function test_tanggal_perubahan_sebelum_tanggal_apply_atau_di_masa_depan_ditolak(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['applied_date' => '2026-09-10']);

        $this->update($user, $job, $this->payload(['applied_date' => '2026-09-10', 'status' => 'screening', 'changed_at' => '2026-09-05']))
            ->assertSessionHasErrors('changed_at');

        $this->update($user, $job, $this->payload(['applied_date' => '2026-09-10', 'status' => 'screening', 'changed_at' => '2026-09-21']))
            ->assertSessionHasErrors('changed_at');

        $this->assertSame(1, $job->statusHistory()->count());
    }

    public function test_status_yang_sama_dengan_riwayat_sebelumnya_pada_tanggal_itu_ditolak(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['status' => 'screening', 'changed_at' => '2026-09-05']);

        // Pada 3 September status loker ini masih Applied
        $this->update($user, $job, $this->payload(['status' => 'applied', 'changed_at' => '2026-09-03']))
            ->assertSessionHasErrors('status');

        $this->assertSame(2, $job->statusHistory()->count());
    }

    public function test_riwayat_disisipkan_di_tanggal_lampau_tanpa_mengubah_status_saat_ini(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['status' => 'screening', 'changed_at' => '2026-09-05']);

        $this->update($user, $job, $this->payload(['status' => 'interview', 'changed_at' => '2026-09-03']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Loker Backend Developer di PT Contoh Maju diperbarui.');

        $this->assertSame(3, $job->statusHistory()->count());
        $this->assertSame('screening', $job->fresh()->current_status->value);
    }

    // ---------------------------------------------------------------
    // Kolom hasil (rejected dan offer)
    // ---------------------------------------------------------------

    public function test_pindah_ke_rejected_menyimpan_alasan(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload([
            'status' => 'rejected',
            'changed_at' => '2026-09-10',
            'rejection_reason' => 'skill_gap',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('skill_gap', $job->fresh()->rejection_reason->value);
    }

    public function test_alasan_penolakan_bisa_diubah_tanpa_mengganti_status(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, [
            'status' => 'rejected',
            'changed_at' => '2026-09-10',
            'rejection_reason' => 'skill_gap',
        ]);

        $other = collect(RejectionReason::cases())->first(fn ($case) => $case->value !== 'skill_gap');

        $this->update($user, $job, $this->payload([
            'status' => 'rejected',
            'rejection_reason' => $other->value,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($other->value, $job->fresh()->rejection_reason->value);
        $this->assertSame(2, $job->statusHistory()->count());
    }

    public function test_keluar_dari_rejected_mengosongkan_alasan_penolakan(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, [
            'status' => 'rejected',
            'changed_at' => '2026-09-10',
            'rejection_reason' => 'skill_gap',
        ]);

        $this->update($user, $job, $this->payload([
            'status' => 'interview',
            'changed_at' => '2026-09-12',
            'rejection_reason' => 'skill_gap',
        ]))->assertSessionHasNoErrors();

        $job = $job->fresh();

        $this->assertSame('interview', $job->current_status->value);
        $this->assertNull($job->rejection_reason);
    }

    public function test_keputusan_dan_gaji_offer_bisa_diubah_tanpa_mengganti_status(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, [
            'status' => 'offer',
            'changed_at' => '2026-09-15',
            'offer_decision' => 'pending',
            'salary_offered' => '15000000',
        ]);

        $this->update($user, $job, $this->payload([
            'status' => 'offer',
            'offer_decision' => 'accepted',
            'salary_offered' => '18.000.000',
        ]))->assertSessionHasNoErrors();

        $job = $job->fresh();

        $this->assertSame('accepted', $job->offer_decision->value);
        $this->assertSame(18000000, $job->salary_offered);
        $this->assertSame(2, $job->statusHistory()->count());
    }

    public function test_keluar_dari_offer_mengosongkan_keputusan_dan_gaji_ditawarkan(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, [
            'status' => 'offer',
            'changed_at' => '2026-09-15',
            'offer_decision' => 'pending',
            'salary_offered' => '15000000',
        ]);

        $this->update($user, $job, $this->payload([
            'status' => 'rejected',
            'changed_at' => '2026-09-18',
            'offer_decision' => 'pending',
            'salary_offered' => '15.000.000',
        ]))->assertSessionHasNoErrors();

        $job = $job->fresh();

        $this->assertNull($job->offer_decision);
        $this->assertNull($job->salary_offered);
    }

    // ---------------------------------------------------------------
    // Tanggal apply dan riwayat awal
    // ---------------------------------------------------------------

    public function test_ubah_tanggal_apply_memindahkan_baris_riwayat_awal(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload(['applied_date' => '2026-08-25']))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-25', $job->fresh()->applied_date->toDateString());
        $this->assertSame(1, $job->statusHistory()->count());
        $this->assertDatabaseHas('status_history', ['job_id' => $job->id, 'status' => 'applied', 'changed_at' => '2026-08-25']);
        $this->assertDatabaseMissing('status_history', ['job_id' => $job->id, 'changed_at' => '2026-09-01']);
    }

    public function test_tanggal_apply_setelah_riwayat_paling_awal_ditolak(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['status' => 'screening', 'changed_at' => '2026-09-05']);

        $this->update($user, $job, $this->payload(['applied_date' => '2026-09-10', 'status' => 'screening']))
            ->assertSessionHasErrors('applied_date');

        $this->assertSame('2026-09-01', $job->fresh()->applied_date->toDateString());
    }

    // ---------------------------------------------------------------
    // Sinkronisasi skill
    // ---------------------------------------------------------------

    public function test_skill_disinkronkan_tambah_ubah_dan_hapus(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['skills' => ['Docker', 'Kubernetes', 'Terraform']]);

        $ids = $job->skillGaps()->pluck('id', 'skill_name');

        // docker: huruf berubah, Kubernetes: tetap, Terraform: dihapus, Redis: baru
        $this->update($user, $job, $this->payload(['skills' => ['docker', 'Kubernetes', 'Redis']]))
            ->assertSessionHasNoErrors();

        $gaps = $job->skillGaps()->get();

        $this->assertEqualsCanonicalizing(['docker', 'Kubernetes', 'Redis'], $gaps->pluck('skill_name')->all());

        // Baris lama dipertahankan (id sama), bukan dihapus lalu dibuat ulang
        $this->assertSame($ids['Docker'], $gaps->firstWhere('skill_name', 'docker')->id);
        $this->assertSame($ids['Kubernetes'], $gaps->firstWhere('skill_name', 'Kubernetes')->id);
        $this->assertDatabaseMissing('job_skill_gaps', ['job_id' => $job->id, 'skill_name' => 'Terraform']);
    }

    public function test_semua_skill_dihapus_jika_tidak_ada_yang_dikirim(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['skills' => ['Docker', 'Kubernetes']]);

        $this->update($user, $job, $this->payload())->assertSessionHasNoErrors();

        $this->assertDatabaseCount('job_skill_gaps', 0);
    }

    public function test_skill_dirapikan_tanpa_duplikat_dan_tanpa_yang_kosong(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $this->update($user, $job, $this->payload([
            'skills' => ['Docker', ' docker ', 'Kubernetes', '', 'DOCKER'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            ['Docker', 'Kubernetes'],
            $job->skillGaps()->orderBy('skill_name')->pluck('skill_name')->all()
        );
    }

    public function test_skill_tidak_menyentuh_loker_lain(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['skills' => ['Docker']]);
        $other = $this->makeJob($user, ['company_name' => 'PT Lain', 'skills' => ['Go']]);

        $this->update($user, $job, $this->payload(['skills' => ['Redis']]))->assertSessionHasNoErrors();

        $this->assertSame(['Go'], $other->skillGaps()->pluck('skill_name')->all());
    }

    public function test_jumlah_dan_panjang_skill_dibatasi(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user);

        $skills = array_map(fn (int $i) => "Skill {$i}", range(1, 21));

        $this->update($user, $job, $this->payload(['skills' => $skills]))->assertSessionHasErrors('skills');
        $this->update($user, $job, $this->payload(['skills' => [str_repeat('a', 101)]]))->assertSessionHasErrors('skills.0');
    }

    // ---------------------------------------------------------------
    // Transaksi
    // ---------------------------------------------------------------

    public function test_semua_dibatalkan_jika_penyimpanan_gagal(): void
    {
        $user = User::factory()->create();
        $job = $this->makeJob($user, ['notes' => 'Lama', 'skills' => ['Docker']]);

        // Paksa penambahan riwayat gagal setelah atribut dan skill sempat diubah
        StatusHistory::creating(function () {
            throw new \RuntimeException('Gagal sengaja untuk uji transaksi');
        });

        try {
            $this->update($user, $job, $this->payload([
                'notes' => 'Baru',
                'status' => 'screening',
                'changed_at' => '2026-09-05',
                'skills' => ['Redis'],
            ]))->assertStatus(500);
        } finally {
            StatusHistory::flushEventListeners();
        }

        $job = $job->fresh();

        $this->assertSame('Lama', $job->notes);
        $this->assertSame('applied', $job->current_status->value);
        $this->assertSame(1, $job->statusHistory()->count());
        $this->assertSame(['Docker'], $job->skillGaps()->pluck('skill_name')->all());
    }
}