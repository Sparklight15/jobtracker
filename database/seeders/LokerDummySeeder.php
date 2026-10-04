<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Enums\CvCustomization;
use App\Enums\IndustrySector;
use App\Enums\OfferDecision;
use App\Enums\RejectionReason;
use App\Enums\WorkMode;
use App\Models\Job;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 50 loker dummy untuk melihat tampilan List Loker (tabel, filter, sorting, pagination).
 *
 * Jalankan: php artisan db:seed --class=LokerDummySeeder
 *
 * Semua loker dummy punya job_url berawal https://contoh.test/lowongan/ sebagai penanda.
 * Menjalankan seeder ini lagi akan menghapus batch dummy lama milik akun yang sama dulu,
 * jadi tidak menumpuk. Loker lain (bukan dummy) tidak disentuh.
 */
class LokerDummySeeder extends Seeder
{
    private const JUMLAH = 50;

    private const PENANDA_URL = 'https://contoh.test/lowongan/';

    /** Peluang status akhir (total 100), dibuat mirip kenyataan: banyak rejected dan ghosted. */
    private const BOBOT_STATUS = [
        'applied' => 22,
        'screening' => 12,
        'interview' => 10,
        'offer' => 4,
        'rejected' => 26,
        'ghosted' => 26,
    ];

    private const PERUSAHAAN = [
        'PT Nusantara Digital', 'PT Cahaya Mandiri', 'PT Sinar Karya Utama', 'CV Bintang Kreatif',
        'PT Garuda Logistik Prima', 'PT Arunika Teknologi', 'PT Lintas Data Indonesia', 'PT Mitra Sehat Sentosa',
        'PT Kreasi Media Nusa', 'PT Samudra Finansial', 'PT Pelita Edukasi', 'PT Bumi Retail Jaya',
        'PT Cakra Konsultindo', 'PT Prima Manufaktur Persada', 'PT Tirta Energi Nusantara', 'PT Karya Digital Solusi',
        'PT Dwi Sukses Makmur', 'PT Rajawali Teknik', 'PT Anggara Konsultan', 'PT Harmoni Pangan',
        'PT Medika Utama Husada', 'PT Visi Kreatif Studio', 'PT Optima Sistem Informasi', 'PT Raya Distribusi',
        'PT Kencana Properti',
    ];

    private const POSISI = [
        'Staff Admin', 'Staff Akuntansi', 'Data Analyst', 'Business Analyst',
        'Digital Marketing Specialist', 'Content Writer', 'Customer Service Officer', 'HR Generalist',
        'Staff Purchasing', 'Junior Web Developer', 'Project Coordinator', 'Staff Finance',
        'Quality Control', 'Operations Staff', 'Graphic Designer', 'Sales Executive',
    ];

    private const KOTA = [
        'Bandung', 'Jakarta', 'Bekasi', 'Tangerang', 'Depok',
        'Bogor', 'Surabaya', 'Yogyakarta', 'Semarang', 'Malang',
    ];

    private const REFERRER = [
        'Rina', 'Dimas', 'Putri', 'Fajar', 'Maya', 'Bagas', 'Sinta', 'Rizki',
    ];

    private const SKILL_KURANG = [
        'SQL', 'Excel Lanjutan', 'Power BI', 'Tableau', 'Python', 'SAP',
        'Google Analytics', 'Bahasa Inggris Aktif', 'Project Management',
        'Presentasi dan Komunikasi', 'Akuntansi Pajak', 'Digital Marketing',
    ];

    private const CATATAN = [
        'Proses seleksi lewat email, belum ada jadwal tes.',
        'HRD ramah, gaji dibahas di tahap interview.',
        'Perlu siapkan portofolio sebelum tes teknis.',
        'Lokasi kantor agak jauh, cek opsi hybrid.',
        'Lowongan ditemukan lewat rekomendasi teman.',
        'Tes online 60 menit, materi logika dan Excel.',
    ];

    public function run(): void
    {
        $default = User::query()->orderBy('id')->value('email');
        $email = strtolower(trim((string) $this->command->ask('Email akun pemilik loker dummy?', $default)));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->command->error("Akun dengan email {$email} tidak ditemukan. Tidak ada data yang dibuat.");

            return;
        }

        DB::transaction(function () use ($user) {
            $this->hapusBatchLama($user);

            $pasangan = collect(self::PERUSAHAAN)
                ->crossJoin(self::POSISI)
                ->shuffle()
                ->take(self::JUMLAH)
                ->values();

            foreach ($pasangan as $urutan => [$perusahaan, $posisi]) {
                $this->buatLoker($user, $perusahaan, $posisi, $urutan + 1);
            }
        });

        $this->command->info(self::JUMLAH." loker dummy dibuat untuk {$user->email}.");
    }

    private function hapusBatchLama(User $user): void
    {
        $idLama = Job::query()
            ->where('user_id', $user->id)
            ->where('job_url', 'like', self::PENANDA_URL.'%')
            ->pluck('id');

        if ($idLama->isEmpty()) {
            return;
        }

        DB::table('status_history')->whereIn('job_id', $idLama)->delete();
        DB::table('job_skill_gaps')->whereIn('job_id', $idLama)->delete();
        Job::query()->whereIn('id', $idLama)->delete();
    }

    private function buatLoker(User $user, string $perusahaan, string $posisi, int $urutan): void
    {
        $statusAkhir = $this->pilihBerbobot(self::BOBOT_STATUS);

        // Loker ghosted harus cukup lama supaya masuk akal (tidak ada kabar berminggu-minggu)
        $tanggalApply = now()->startOfDay()->subDays(
            $statusAkhir === 'ghosted' ? random_int(25, 120) : random_int(0, 120)
        );

        $riwayat = $this->buatRiwayat($statusAkhir, $tanggalApply);

        // Respons pertama = perubahan status pertama setelah apply (kecuali langsung ghosted)
        $responPertama = isset($riwayat[1]) && $riwayat[1][0] !== 'ghosted'
            ? $riwayat[1][1]->toDateString()
            : null;

        $channel = Arr::random(Channel::cases())->value;
        $pakaiReferral = $channel === 'referral' || random_int(1, 100) <= 8;

        $punyaGaji = random_int(1, 100) <= 80;
        $gajiMin = $punyaGaji ? random_int(10, 32) * 500_000 : null;
        $gajiMax = $punyaGaji ? $gajiMin + random_int(4, 16) * 500_000 : null;
        $gajiDitawarkan = ($statusAkhir === 'offer' && $punyaGaji)
            ? $gajiMin + random_int(0, intdiv($gajiMax - $gajiMin, 500_000)) * 500_000
            : null;

        $dibuat = $tanggalApply->copy()->setTime(random_int(8, 20), random_int(0, 59));

        $job = Job::forceCreate([
            'user_id' => $user->id,
            'company_name' => $perusahaan,
            'position' => $posisi,
            'job_url' => self::PENANDA_URL.Str::slug("{$perusahaan} {$posisi}")."-{$urutan}",
            'current_status' => $statusAkhir,
            'applied_date' => $tanggalApply->toDateString(),
            'first_response_date' => $responPertama,
            'channel' => $channel,
            'has_referral' => $pakaiReferral,
            'referrer_name' => $pakaiReferral ? Arr::random(self::REFERRER) : null,
            'industry_sector' => Arr::random(IndustrySector::cases())->value,
            'city' => random_int(1, 100) <= 90 ? Arr::random(self::KOTA) : null,
            'work_mode' => Arr::random(WorkMode::cases())->value,
            'salary_min' => $gajiMin,
            'salary_max' => $gajiMax,
            'salary_offered' => $gajiDitawarkan,
            'fit_score' => random_int(1, 100) <= 90 ? random_int(1, 5) : null,
            'skill_match_score' => random_int(1, 100) <= 90 ? random_int(1, 5) : null,
            'cv_customization' => Arr::random(CvCustomization::cases())->value,
            'rejection_reason' => $statusAkhir === 'rejected' ? Arr::random(RejectionReason::cases())->value : null,
            'offer_decision' => $statusAkhir === 'offer' ? Arr::random(OfferDecision::cases())->value : null,
            'notes' => random_int(1, 100) <= 40 ? Arr::random(self::CATATAN) : null,
            'created_at' => $dibuat,
            'updated_at' => $dibuat,
        ]);

        // Riwayat status: baris pertama selalu "applied" (sesuai aturan data 6.6)
        DB::table('status_history')->insert(
            array_map(fn (array $baris) => [
                'job_id' => $job->id,
                'status' => $baris[0],
                'changed_at' => $baris[1]->toDateString(),
                'created_at' => $baris[1]->copy()->setTime(9, 0),
            ], $riwayat)
        );

        // Sekitar separuh loker punya skill yang kurang (1-3 skill)
        if (random_int(1, 100) <= 55) {
            DB::table('job_skill_gaps')->insert(
                array_map(fn (string $skill) => [
                    'job_id' => $job->id,
                    'skill_name' => $skill,
                ], Arr::random(self::SKILL_KURANG, random_int(1, 3)))
            );
        }
    }

    /**
     * Susun alur status dari "applied" sampai status akhir, tiap langkah beberapa hari kemudian.
     *
     * @return array<int, array{0: string, 1: Carbon}>
     */
    private function buatRiwayat(string $statusAkhir, Carbon $tanggalApply): array
    {
        $alur = match ($statusAkhir) {
            'applied' => ['applied'],
            'screening' => ['applied', 'screening'],
            'interview' => ['applied', 'screening', 'interview'],
            'offer' => ['applied', 'screening', 'interview', 'offer'],
            'rejected' => Arr::random([
                ['applied', 'rejected'],
                ['applied', 'screening', 'rejected'],
                ['applied', 'screening', 'interview', 'rejected'],
            ]),
            'ghosted' => Arr::random([
                ['applied', 'ghosted'],
                ['applied', 'screening', 'ghosted'],
            ]),
        };

        $hariIni = now()->startOfDay();
        $tanggal = $tanggalApply->copy();
        $hasil = [];

        foreach ($alur as $indeks => $status) {
            if ($indeks > 0) {
                $loncat = $status === 'ghosted' ? random_int(14, 28) : random_int(3, 14);
                $tanggal = $tanggal->copy()->addDays($loncat);

                // Tanggal perubahan status tidak boleh melewati hari ini
                if ($tanggal->gt($hariIni)) {
                    $tanggal = $hariIni->copy();
                }
            }

            $hasil[] = [$status, $tanggal->copy()];
        }

        return $hasil;
    }

    private function pilihBerbobot(array $bobot): string
    {
        $acak = random_int(1, array_sum($bobot));

        foreach ($bobot as $nilai => $b) {
            $acak -= $b;

            if ($acak <= 0) {
                return $nilai;
            }
        }

        return array_key_first($bobot);
    }
}