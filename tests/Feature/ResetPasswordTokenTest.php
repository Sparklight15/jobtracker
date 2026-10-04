<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTokenTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(): User
    {
        return User::forceCreate([
            'email' => 'uji@jobtracker.test',
            'password' => bcrypt('rahasia123'),
        ]);
    }

    private function urlReset(string $token, string $email): string
    {
        return route('password.reset', ['token' => $token, 'email' => $email]);
    }

    private function dataReset(User $user, string $token): array
    {
        return [
            'token' => $token,
            'email' => $user->email,
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ];
    }

    public function test_tautan_valid_menampilkan_form(): void
    {
        $user = $this->buatUser();
        $token = Password::createToken($user);

        $this->get($this->urlReset($token, $user->email))
            ->assertOk()
            ->assertSee('Simpan kata sandi');
    }

    public function test_tautan_kedaluwarsa_setelah_60_menit(): void
    {
        $user = $this->buatUser();
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->get($this->urlReset($token, $user->email))
            ->assertOk()
            ->assertSee('Tautan sudah kedaluwarsa')
            ->assertDontSee('Simpan kata sandi');
    }

    public function test_tautan_masih_berlaku_di_menit_ke_59(): void
    {
        $user = $this->buatUser();
        $token = Password::createToken($user);

        $this->travel(59)->minutes();

        $this->get($this->urlReset($token, $user->email))
            ->assertSee('Simpan kata sandi');
    }

    public function test_simpan_dengan_token_kedaluwarsa_ditolak(): void
    {
        $user = $this->buatUser();
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->post('/reset-password', $this->dataReset($user, $token))
            ->assertRedirect($this->urlReset($token, $user->email));

        $this->assertTrue(\Hash::check('rahasia123', $user->fresh()->password));
    }

    public function test_token_hanya_bisa_dipakai_sekali(): void
    {
        $user = $this->buatUser();
        $token = Password::createToken($user);

        $this->post('/reset-password', $this->dataReset($user, $token))
            ->assertRedirect(route('login'));

        // Pemakaian kedua ditolak
        $this->post('/reset-password', $this->dataReset($user, $token))
            ->assertRedirect($this->urlReset($token, $user->email));

        $this->get($this->urlReset($token, $user->email))
            ->assertSee('Tautan tidak berlaku');
    }

    public function test_token_salah_menampilkan_tautan_tidak_berlaku(): void
    {
        $user = $this->buatUser();
        Password::createToken($user);

        $this->get($this->urlReset('token-ngawur', $user->email))
            ->assertSee('Tautan tidak berlaku');
    }

    public function test_tautan_baru_membatalkan_tautan_lama(): void
    {
        $user = $this->buatUser();
        $lama = Password::createToken($user);
        Password::createToken($user);

        $this->get($this->urlReset($lama, $user->email))
            ->assertSee('Tautan tidak berlaku');
    }
}