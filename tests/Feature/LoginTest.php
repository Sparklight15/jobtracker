<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $email = 'uji@jobtracker.test'): User
    {
        return User::forceCreate([
            'email' => $email,
            'password' => bcrypt('rahasia123'),
        ]);
    }

    private function salah(string $email, int $kali): void
    {
        for ($i = 0; $i < $kali; $i++) {
            $this->post('/login', ['email' => $email, 'password' => 'salah']);
        }
    }

    public function test_halaman_login_tampil(): void
    {
        $this->get('/login')->assertOk()->assertSee('Kata sandi');
    }

    public function test_login_berhasil_diarahkan_ke_beranda(): void
    {
        $user = $this->buatUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect('/beranda');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_gagal_kata_sandi_salah(): void
    {
        $user = $this->buatUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors(['password' => trans('auth.password_wrong')]);

        $this->assertGuest();
    }

    public function test_login_gagal_email_tidak_terdaftar(): void
    {
        $this->post('/login', ['email' => 'tidakada@jobtracker.test', 'password' => 'apa saja'])
            ->assertSessionHasErrors(['email' => trans('auth.email_not_found')]);

        $this->assertGuest();
    }

    public function test_login_gagal_jika_kolom_kosong(): void
    {
        $this->post('/login', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_terkunci_setelah_lima_kali_salah(): void
    {
        $user = $this->buatUser();

        $this->salah($user->email, 5);

        // Percobaan ke-6 ditolak walau kata sandinya benar
        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('throttle')
            ->assertSessionHas('throttle_until');

        $this->assertGuest();
    }

    public function test_email_lain_tidak_ikut_terkunci(): void
    {
        $a = $this->buatUser('a@jobtracker.test');
        $b = $this->buatUser('b@jobtracker.test');

        $this->salah($a->email, 5);

        $this->post('/login', ['email' => $b->email, 'password' => 'rahasia123'])
            ->assertRedirect('/beranda');

        $this->assertAuthenticatedAs($b);
    }

    public function test_login_berhasil_mereset_hitungan_email(): void
    {
        $user = $this->buatUser();

        $this->salah($user->email, 3);
        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect('/beranda');
        $this->post('/logout');

        // Hitungan sudah nol: lima kali salah lagi tetap "kata sandi salah", belum terkunci
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah'])
                ->assertSessionHasErrors('password')
                ->assertSessionDoesntHaveErrors('throttle');
        }
    }

    public function test_batas_per_ip_mengunci_walau_email_berganti(): void
    {
        // 20 percobaan dengan email berbeda-beda, masing-masing hanya sekali
        for ($i = 1; $i <= 20; $i++) {
            $this->post('/login', ['email' => "orang{$i}@jobtracker.test", 'password' => 'x']);
        }

        $this->post('/login', ['email' => 'orang21@jobtracker.test', 'password' => 'x'])
            ->assertSessionHasErrors('throttle');
    }
}