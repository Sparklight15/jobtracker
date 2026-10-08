<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-stat-card name="prediction_score" label="Skor Prediksi Keberhasilan" />
    <x-stat-card name="active_jobs" label="Lamaran Aktif" />
    <x-stat-card name="historical_conversion" label="Conversion Historis" />
</div>

<div class="mt-4 grid gap-4">
    <div>
        <div class="relative">
            <x-chart-panel name="funnel_trend" title="Tren funnel per minggu apply" />

            <x-info-popover
                title="Tren funnel per minggu apply"
                about="Sebaran posisi lamaranmu di akhir tiap minggu, maksimal 12 minggu terakhir. Setiap batang ditumpuk dari empat kelompok: Applied, Interview, Offer, dan Rejected."
                how="Applied sudah termasuk Screening, dan Rejected sudah termasuk Ghosted. Tinggi satu batang adalah total lamaran yang punya riwayat sampai minggu itu."
                insight="Perhatikan apakah porsi Interview dan Offer membesar dari minggu ke minggu. Kalau yang membesar hanya Applied atau Rejected, strategi apply-mu perlu diubah." />
        </div>

        <p class="mt-3 text-sm opacity-70">
            Tiap titik menunjukkan berapa lamaran yang dikirim pada minggu itu dan pernah mencapai tiap tahap.
            Lamaran minggu-minggu terakhir belum sempat diproses, jadi garis tahap lanjut di ujung kanan cenderung rendah.
        </p>
    </div>
</div>