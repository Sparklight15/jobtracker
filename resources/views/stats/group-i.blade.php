<div class="grid gap-4 sm:grid-cols-2">
    <x-stat-card name="prediction_score" label="Skor Prediksi Keberhasilan" />
</div>

<div class="mt-4 grid gap-4">
    <div>
        <x-chart-panel name="funnel_trend" title="Tren funnel per minggu apply" />
        <p class="mt-3 text-sm opacity-70">
            Tiap titik menunjukkan berapa lamaran yang dikirim pada minggu itu dan pernah mencapai tiap tahap.
            Lamaran minggu-minggu terakhir belum sempat diproses, jadi garis tahap lanjut di ujung kanan cenderung rendah.
        </p>
    </div>
</div>