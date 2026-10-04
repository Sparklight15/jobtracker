<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(): User
    {
        return User::forceCreate([
            'email' => 'uji@jobtracker.test',
            'password' => bcrypt('rahasia123'),
        ]);
    }

    public function test_email_terdaftar_dan_tidak_terdaftar_mendapat_respons_sama(): void
    {
        Notification::fake();
        $this->buatUser();

        $terdaftar = $this->post('/forgot-password', ['email' => 'uji@jobtracker.test']);
        $tidakTerdaftar = $this->post('/forgot-password', ['email' => 'tidakada@jobtracker.test']);

        foreach ([$terdaftar, $tidakTerdaftar] as $respons) {
            $respons->assertRedirect()
                ->assertSessionHas('reset_link_sent', true)
                ->assertSessionHasNoErrors();
        }
    }

    public function test_email_dikirim_hanya_untuk_akun_terdaftar(): void
    {
        Notification::fake();
        $user = $this->buatUser();

        $this->post('/forgot-password', ['email' => $user->email]);
        Notification::assertSentTo($user, ResetPassword::class);

        Notification::fake();
        $this->post('/forgot-password', ['email' => 'tidakada@jobtracker.test']);
        Notification::assertNothingSent();
    }

    public function test_kirim_ulang_dalam_60_detik_tetap_netral(): void
    {
        Notification::fake();
        $user = $this->buatUser();

        $this->post('/forgot-password', ['email' => $user->email]);
        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('reset_link_sent', true)
            ->assertSessionHasNoErrors();
    }

    public function test_format_email_salah_tetap_ditolak(): void
    {
        $this->post('/forgot-password', ['email' => 'bukan-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_dibatasi_lima_permintaan_per_menit(): void
    {
        Notification::fake();

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/forgot-password', ['email' => "orang{$i}@jobtracker.test"])
                ->assertSessionHasNoErrors();
        }

        $this->post('/forgot-password', ['email' => 'orang6@jobtracker.test'])
            ->assertStatus(429);
    }
}