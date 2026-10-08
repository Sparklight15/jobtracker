<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-stat-card name="total_apply" label="Total apply" />
    <x-stat-card name="overall_conversion" label="Conversion keseluruhan" />
    <x-stat-card name="ghosting_rate" label="Rasio ghosting" />
</div>

{{-- 2 kolom di layar menengah (sidebar terbuka membuat 3 kolom terlalu sempit), 3 kolom mulai xl.
     Tiap grafik dibungkus div relative supaya tombol info (!) bisa menempel di pojok kanan atas kartu. --}}
<div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">

    <div class="relative">
        <x-chart-panel name="per_stage" title="Lamaran per tahap" />

        <x-info-popover
            title="Lamaran per tahap"
            visual="funnel"
            about="Jumlah lamaranmu yang pernah sampai ke tiap tahap, dari Applied sampai Offer."
            how="Batang paling kiri adalah semua lamaranmu. Makin ke kanan biasanya makin pendek."
            insight="Cari tahap di mana batangnya turun paling tajam. Di situlah lamaranmu paling banyak gugur." />
    </div>

    <div class="relative">
        <x-chart-panel name="stage_conversion" title="Conversion antar tahap" />

        <x-info-popover
            title="Conversion antar tahap"
            visual="hbars"
            about="Persentase lamaran yang lanjut dari satu tahap ke tahap berikutnya."
            how="Tiap irisan adalah satu perpindahan tahap. Makin jauh irisan menjulur keluar, makin besar peluang lolos. 50% berarti 5 dari 10 lamaran lanjut."
            insight="Irisan yang paling pendek adalah titik terlemahmu. Perbaiki bagian itu dulu." />
    </div>

    <div class="relative md:col-span-2 xl:col-span-1">
        <x-chart-panel name="status_now" title="Status saat ini" />

        <x-info-popover
            title="Status saat ini"
            visual="status"
            about="Posisi semua lamaranmu hari ini. Satu lamaran dihitung sekali, di status terakhirnya."
            how="Applied, Screening, dan Interview masih berjalan. Offer, Rejected, dan Ghosted sudah selesai."
            insight="Lamaran yang masih berjalan perlu kamu pantau. Kalau Ghosted tinggi, coba follow up lebih cepat." />
    </div>

</div>