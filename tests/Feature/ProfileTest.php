<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(array $atribut = []): User
    {
        return User::forceCreate(array_merge([
            'email' => 'uji@jobtracker.test',
            'password' => bcrypt('rahasia123'),
        ], $atribut));
    }

    /** Email tidak ada di sini: field-nya disabled dan tidak ikut terkirim. */
    private function dataValid(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'job_search_started_at' => '2026-09-01',
            'apply_target' => 10,
            'apply_target_period' => 'week',
        ], $ubah);
    }

    private function tambahLoker(User $user): void
    {
        $user->jobs()->create([
            'company_name' => 'PT Contoh',
            'position' => 'Backend Developer',
            'current_status' => 'applied',
            'applied_date' => '2026-10-01',
            'channel' => 'other',
        ]);
    }

    // ---------- Halaman ----------

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_halaman_profil_tampil_dengan_data_pengguna(): void
    {
        $user = $this->buatUser(['name' => 'Budi Santoso', 'apply_target' => 5, 'apply_target_period' => 'month']);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Info akun')
            ->assertSee('Budi Santoso')
            ->assertSee($user->email)
            ->assertSee('Per bulan')
            ->assertSee('Kirim link reset password');
    }

    public function test_field_email_di_halaman_profil_disabled(): void
    {
        $html = $this->actingAs($this->buatUser())->get('/profile')->getContent();

        $this->assertSame(1, preg_match('/<input\b[^>]*\bname="email"[^>]*>/s', $html, $cocok));
        $this->assertStringContainsString('disabled', $cocok[0]);
    }

    // ---------- Update profil ----------

    public function test_update_profil_berhasil(): void
    {
        $user = $this->buatUser();

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', $this->dataValid())
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile')
            ->assertSessionHas('status', 'profile-updated');

        $user->refresh();
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertSame(10, $user->apply_target);
        $this->assertSame('week', $user->apply_target_period->value);
        $this->assertSame('2026-09-01', $user->job_search_started_at->toDateString());
    }

    public function test_email_tidak_berubah_walau_dikirim_lewat_request(): void
    {
        $user = $this->buatUser();

        $this->actingAs($user)
            ->patch('/profile', $this->dataValid(['email' => 'baru@jobtracker.test']))
            ->assertSessionHasNoErrors();

        $this->assertSame('uji@jobtracker.test', $user->fresh()->email);
    }

    public function test_nama_boleh_kosong(): void
    {
        $user = $this->buatUser(['name' => 'Lama']);

        $this->actingAs($user)
            ->patch('/profile', $this->dataValid(['name' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->name);
    }

    public function test_nama_maksimal_100_karakter(): void
    {
        $this->actingAs($this->buatUser())
            ->patch('/profile', $this->dataValid(['name' => str_repeat('a', 101)]))
            ->assertSessionHasErrors('name');
    }

    public function test_target_tanpa_periode_ditolak(): void
    {
        $this->actingAs($this->buatUser())
            ->patch('/profile', $this->dataValid(['apply_target_period' => '']))
            ->assertSessionHasErrors('apply_target_period');
    }

    public function test_periode_tanpa_target_ditolak(): void
    {
        $this->actingAs($this->buatUser())
            ->patch('/profile', $this->dataValid(['apply_target' => '']))
            ->assertSessionHasErrors('apply_target');
    }

    public function test_target_dan_periode_boleh_dikosongkan_bersamaan(): void
    {
        $user = $this->buatUser(['apply_target' => 5, 'apply_target_period' => 'week']);

        $this->actingAs($user)
            ->patch('/profile', $this->dataValid(['apply_target' => '', 'apply_target_period' => '']))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->apply_target);
        $this->assertNull($user->apply_target_period);
    }

    public function test_target_di_luar_batas_ditolak(): void
    {
        $user = $this->buatUser();

        $this->actingAs($user)
            ->patch('/profile', $this->dataValid(['apply_target' => 0]))
            ->assertSessionHasErrors('apply_target');

        $this->actingAs($user)
            ->patch('/profile', $this->dataValid(['apply_target' => 1001]))
            ->assertSessionHasErrors('apply_target');
    }

    public function test_periode_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->buatUser())
            ->patch('/profile', $this->dataValid(['apply_target_period' => 'year']))
            ->assertSessionHasErrors('apply_target_period');
    }

    public function test_tanggal_mulai_di_masa_depan_ditolak(): void
    {
        $this->actingAs($this->buatUser())
            ->patch('/profile', $this->dataValid(['job_search_started_at' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('job_search_started_at');
    }

    // ---------- Reset kata sandi lewat email ----------

    public function test_tamu_tidak_bisa_meminta_link_reset_dari_profil(): void
    {
        Notification::fake();

        $this->post('/profile/password-reset-link')->assertRedirect('/login');

        Notification::assertNothingSent();
    }

    public function test_kirim_link_reset_ke_email_akun_yang_login(): void
    {
        Notification::fake();
        $user = $this->buatUser();

        $this->actingAs($user)
            ->from('/profile')
            ->post('/profile/password-reset-link')
            ->assertRedirect('/profile')
            ->assertSessionHas('status', 'password-reset-link-sent');

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_link_reset_tidak_terkirim_ke_email_lain(): void
    {
        Notification::fake();
        $user = $this->buatUser();
        $lain = $this->buatUser(['email' => 'lain@jobtracker.test']);

        $this->actingAs($user)->post('/profile/password-reset-link', ['email' => $lain->email]);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertNotSentTo($lain, ResetPassword::class);
    }

    public function test_permintaan_link_reset_berulang_ditahan(): void
    {
        Notification::fake();
        $user = $this->buatUser();

        $this->actingAs($user)->from('/profile')->post('/profile/password-reset-link');

        $this->actingAs($user)
            ->from('/profile')
            ->post('/profile/password-reset-link')
            ->assertRedirect('/profile')
            ->assertSessionHasErrorsIn('passwordReset', 'password_reset');

        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_pengguna_login_bisa_membuka_halaman_reset_dari_link_email(): void
    {
        $this->actingAs($this->buatUser())
            ->get(route('password.reset', ['token' => 'token-uji']))
            ->assertOk();
    }

    // ---------- Hapus akun ----------

    public function test_hapus_akun_berhasil_beserta_datanya(): void
    {
        $user = $this->buatUser();
        $this->tambahLoker($user);

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'rahasia123'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_hapus_akun_gagal_jika_sandi_salah(): void
    {
        $user = $this->buatUser();
        $this->tambahLoker($user);

        $this->actingAs($user)
            ->from('/profile')
            ->delete('/profile', ['password' => 'salah'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_hapus_akun_tidak_menghapus_data_pengguna_lain(): void
    {
        $user = $this->buatUser();
        $lain = $this->buatUser(['email' => 'lain@jobtracker.test']);
        $this->tambahLoker($lain);

        $this->actingAs($user)->delete('/profile', ['password' => 'rahasia123']);

        $this->assertDatabaseHas('users', ['id' => $lain->id]);
        $this->assertDatabaseCount('jobs', 1);
    }
}