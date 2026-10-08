<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-stat-card name="first_response_days" label="Waktu ke respons pertama" />
    <x-stat-card name="search_duration" label="Durasi pencarian kerja" />
    <x-stat-card name="apply_pace" label="Rata-rata apply per minggu" />
</div>

{{-- Tiap grafik dibungkus div relative supaya tombol info (!) menempel di pojok kanan atas kartu.
     Atribut name pada x-chart-panel HARUS sama dengan key di GroupB::compute() -> 'charts'. --}}
<div class="mt-4 grid gap-4">

    <div class="relative">
        <x-chart-panel name="apply_trend" title="Tren apply" />

        <x-info-popover
            title="Tren apply"
            about="Jumlah lamaran yang kamu kirim per hari, per minggu, atau per bulan. Pilih rentangnya lewat tombol Hari, Minggu, atau Bulan di atas grafik."
            how="Setiap titik adalah satu hari, satu minggu (mulai Senin), atau satu bulan, tergantung tombol yang dipilih. Hari menampilkan maksimal 30 hari terakhir, Minggu 26 minggu, dan Bulan 12 bulan. Titik pertama pada rentang bisa terpotong sebagian, jadi angkanya bisa lebih kecil."
            insight="Lihat apakah ritme apply-mu konsisten. Pakai Hari untuk melihat kebiasaan harian, Minggu untuk ritme, dan Bulan untuk gambaran besar. Periode kosong beruntun biasanya terasa dampaknya beberapa minggu kemudian, saat jumlah respons ikut turun." />
    </div>

    {{-- Dua grafik bersebelahan di layar lebar, bertumpuk di HP. kedua kartu dibuat sama tinggi lewat
         [&>[data-chart-panel]]:h-full pada pembungkusnya. --}}
    <div class="grid gap-4 lg:grid-cols-2">

    <div class="relative [&>[data-chart-panel]]:h-full">
        <x-chart-panel name="stage_duration" title="Rata-rata lama tiap tahap" />

        <x-info-popover
            title="Rata-rata lama tiap tahap"
            about="Rata-rata berapa hari lamaranmu berada di tiap tahap (Applied, Screening, Interview) sebelum pindah ke tahap berikutnya."
            how="Makin panjang batangnya, makin lama lamaran menunggu di tahap itu. Hanya tahap yang sudah selesai yang dihitung, dan tahap dengan data terlalu sedikit dikosongkan."
            insight="Tahap dengan batang terpanjang adalah tempat lamaranmu paling lama menunggu. Kalau sudah lewat dari rata-rata, itu saat yang pas untuk follow up." />
    </div>

    <div class="relative [&>[data-chart-panel]]:h-full">
        <x-activity-calendar name="activity_calendar" title="Kalender aktivitas" />

        <x-info-popover
            title="Kalender aktivitas"
            about="Aktivitas lamaranmu hari demi hari: kapan kamu apply, lolos screening, wawancara, dapat offer, ditolak, atau di-ghosting."
            how="Satu kotak adalah satu hari dan angka di dalamnya adalah tanggalnya. Satu baris adalah satu minggu (Senin di kiri, Minggu di kanan), dengan minggu terbaru di paling atas. Geser ke bawah untuk melihat hari-hari sebelumnya, sampai sekitar 5 tahun ke belakang. Warna kotak menunjukkan statusnya, dari paling gelap ke paling terang: Offer, Interview, Screening, Applied, Rejected, lalu Ghosted. Cocokkan dengan legenda di bawah kalender. Kalau satu hari punya beberapa jenis aktivitas, kotaknya dibagi (maksimal tiga bagian). Arahkan kursor atau tap kotak untuk melihat detailnya."
            insight="Hari kosong beruntun menandakan ritme apply yang putus. Kotak gelap yang muncul beberapa hari setelah deretan kotak terang menunjukkan berapa lama lamaran biasanya butuh untuk bergerak maju." />
    </div>

    </div>

</div>