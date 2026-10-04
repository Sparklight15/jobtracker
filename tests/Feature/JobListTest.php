<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobListTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $email = 'uji@jobtracker.test'): User
    {
        return User::forceCreate([
            'email' => $email,
            'password' => bcrypt('rahasia123'),
        ]);
    }

    private function buatLoker(User $user, array $atribut = []): Job
    {
        return Job::factory()->create(array_merge(['user_id' => $user->id], $atribut));
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get(route('jobs.index'))->assertRedirect(route('login'));
    }

    public function test_hanya_menampilkan_loker_milik_sendiri(): void
    {
        $saya = $this->buatUser();
        $lain = $this->buatUser('lain@jobtracker.test');

        $this->buatLoker($saya, ['company_name' => 'PT Milik Saya']);
        $this->buatLoker($lain, ['company_name' => 'PT Milik Orang Lain']);

        $this->actingAs($saya)
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('PT Milik Saya')
            ->assertDontSee('PT Milik Orang Lain');
    }

    public function test_filter_mode_salah_satu_tidak_membocorkan_loker_user_lain(): void
    {
        $saya = $this->buatUser();
        $lain = $this->buatUser('lain@jobtracker.test');

        $this->buatLoker($saya, ['company_name' => 'PT Milik Saya', 'current_status' => 'applied']);
        $this->buatLoker($lain, ['company_name' => 'PT Milik Orang Lain', 'current_status' => 'applied']);

        $url = route('jobs.index', [
            'match' => 'any',
            'filter' => [
                ['field' => 'current_status', 'op' => 'is', 'value' => 'applied'],
                ['field' => 'city', 'op' => 'equals', 'value' => 'Kota Tidak Ada'],
            ],
        ]);

        $this->actingAs($saya)
            ->get($url)
            ->assertOk()
            ->assertSee('PT Milik Saya')
            ->assertDontSee('PT Milik Orang Lain');
    }

    public function test_pencarian_berdasarkan_perusahaan_dan_posisi(): void
    {
        $user = $this->buatUser();

        $this->buatLoker($user, ['company_name' => 'PT Alfa Teknologi', 'position' => 'Staff Admin']);
        $this->buatLoker($user, ['company_name' => 'PT Beta Industri', 'position' => 'Analis Data']);

        $this->actingAs($user)
            ->get(route('jobs.index', ['q' => 'Alfa']))
            ->assertSee('PT Alfa Teknologi')
            ->assertDontSee('PT Beta Industri');

        $this->actingAs($user)
            ->get(route('jobs.index', ['q' => 'Analis']))
            ->assertSee('PT Beta Industri')
            ->assertDontSee('PT Alfa Teknologi');
    }

    public function test_filter_status(): void
    {
        $user = $this->buatUser();

        $this->buatLoker($user, ['company_name' => 'PT Sedang Interview', 'current_status' => 'interview']);
        $this->buatLoker($user, ['company_name' => 'PT Sudah Ditolak', 'current_status' => 'rejected']);

        $url = route('jobs.index', [
            'filter' => [['field' => 'current_status', 'op' => 'is', 'value' => 'interview']],
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertSee('PT Sedang Interview')
            ->assertDontSee('PT Sudah Ditolak');
    }

    public function test_mode_semua_kondisi_dan_salah_satu_kondisi(): void
    {
        $user = $this->buatUser();

        $this->buatLoker($user, ['company_name' => 'PT Satu', 'current_status' => 'applied', 'city' => 'Bandung']);
        $this->buatLoker($user, ['company_name' => 'PT Dua', 'current_status' => 'applied', 'city' => 'Jakarta']);
        $this->buatLoker($user, ['company_name' => 'PT Tiga', 'current_status' => 'rejected', 'city' => 'Bandung']);

        $filter = [
            ['field' => 'current_status', 'op' => 'is', 'value' => 'applied'],
            ['field' => 'city', 'op' => 'equals', 'value' => 'Bandung'],
        ];

        $this->actingAs($user)
            ->get(route('jobs.index', ['match' => 'all', 'filter' => $filter]))
            ->assertSee('PT Satu')
            ->assertDontSee('PT Dua')
            ->assertDontSee('PT Tiga');

        $this->actingAs($user)
            ->get(route('jobs.index', ['match' => 'any', 'filter' => $filter]))
            ->assertSee('PT Satu')
            ->assertSee('PT Dua')
            ->assertSee('PT Tiga');
    }

    public function test_filter_rentang_tanggal_apply(): void
    {
        $user = $this->buatUser();

        $this->buatLoker($user, ['company_name' => 'PT Awal', 'applied_date' => '2026-09-01']);
        $this->buatLoker($user, ['company_name' => 'PT Tengah', 'applied_date' => '2026-09-15']);
        $this->buatLoker($user, ['company_name' => 'PT Akhir', 'applied_date' => '2026-10-01']);

        $url = route('jobs.index', [
            'filter' => [[
                'field' => 'applied_date',
                'op' => 'between',
                'value' => '2026-09-10',
                'value2' => '2026-09-30',
            ]],
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertSee('PT Tengah')
            ->assertDontSee('PT Awal')
            ->assertDontSee('PT Akhir');
    }

    public function test_filter_angka_gaji_ditawarkan_minimal(): void
    {
        $user = $this->buatUser();

        $this->buatLoker($user, ['company_name' => 'PT Gaji Besar', 'salary_offered' => 12000000]);
        $this->buatLoker($user, ['company_name' => 'PT Gaji Kecil', 'salary_offered' => 5000000]);

        $url = route('jobs.index', [
            'filter' => [['field' => 'salary_offered', 'op' => 'gte', 'value' => '10000000']],
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertSee('PT Gaji Besar')
            ->assertDontSee('PT Gaji Kecil');
    }

    public function test_filter_atribut_tidak_dikenal_diabaikan_tanpa_error(): void
    {
        $user = $this->buatUser();
        $this->buatLoker($user, ['company_name' => 'PT Tetap Tampil']);

        $url = route('jobs.index', [
            'sort' => 'password',
            'dir' => 'sideways',
            'filter' => [
                ['field' => 'password', 'op' => 'contains', 'value' => 'x'],
                ['field' => 'city', 'op' => 'drop_table', 'value' => 'x'],
                'bukan-array',
            ],
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertSee('PT Tetap Tampil');
    }

    public function test_sorting_berdasarkan_nama_perusahaan(): void
    {
        $user = $this->buatUser();

        $this->buatLoker($user, ['company_name' => 'PT Zeta']);
        $this->buatLoker($user, ['company_name' => 'PT Alfa']);

        $this->actingAs($user)
            ->get(route('jobs.index', ['sort' => 'company_name', 'dir' => 'asc']))
            ->assertSeeInOrder(['PT Alfa', 'PT Zeta']);

        $this->actingAs($user)
            ->get(route('jobs.index', ['sort' => 'company_name', 'dir' => 'desc']))
            ->assertSeeInOrder(['PT Zeta', 'PT Alfa']);
    }

    public function test_pagination_sepuluh_loker_per_halaman(): void
    {
        $user = $this->buatUser();

        foreach (range(1, 12) as $i) {
            $this->buatLoker($user, ['company_name' => "PT Contoh {$i}"]);
        }

        $halaman1 = $this->actingAs($user)->get(route('jobs.index'))->assertOk();
        $this->assertCount(10, $halaman1->viewData('jobs'));
        $this->assertSame(12, $halaman1->viewData('jobs')->total());

        $halaman2 = $this->actingAs($user)->get(route('jobs.index', ['page' => 2]))->assertOk();
        $this->assertCount(2, $halaman2->viewData('jobs'));
    }

    public function test_halaman_di_luar_jangkauan_dialihkan_ke_halaman_terakhir(): void
    {
        $user = $this->buatUser();
        $this->buatLoker($user);

        $this->actingAs($user)
            ->get(route('jobs.index', ['page' => 5]))
            ->assertRedirect(route('jobs.index', ['page' => 1]));
    }

    public function test_empty_state_saat_belum_ada_loker(): void
    {
        $user = $this->buatUser();

        $this->actingAs($user)
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Belum ada loker')
            ->assertSee('Tambah Loker');
    }

    public function test_empty_state_saat_pencarian_tidak_menemukan_apa_pun(): void
    {
        $user = $this->buatUser();
        $this->buatLoker($user, ['company_name' => 'PT Ada']);

        $this->actingAs($user)
            ->get(route('jobs.index', ['q' => 'tidak-akan-ketemu']))
            ->assertOk()
            ->assertSee('Tidak ada loker yang cocok')
            ->assertDontSee('Belum ada loker');
    }
}