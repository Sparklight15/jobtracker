<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_dialihkan_ke_login_saat_buka_beranda(): void
    {
        $this->get('/beranda')->assertRedirect(route('login'));
    }

    public function test_pengguna_login_bisa_buka_beranda(): void
    {
        $user = User::forceCreate([
            'email' => 'uji@jobtracker.test',
            'password' => bcrypt('rahasia123'),
        ]);

        $this->actingAs($user)->get('/beranda')->assertOk();
    }

    public function test_pengguna_login_dialihkan_dari_halaman_login(): void
    {
        $user = User::forceCreate([
            'email' => 'uji@jobtracker.test',
            'password' => bcrypt('rahasia123'),
        ]);

        $this->actingAs($user)->get('/login')->assertRedirect('/beranda');
    }

    public function test_logout_hanya_untuk_pengguna_login(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }
}