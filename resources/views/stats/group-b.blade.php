<div class="grid gap-4 sm:grid-cols-2">
    <x-stat-card name="first_response_days" label="Waktu ke respons pertama" />
    <x-stat-card name="search_duration" label="Durasi pencarian kerja" />
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <x-chart-panel name="stage_duration" title="Rata-rata lama tiap tahap" />
    <x-chart-panel name="apply_monthly" title="Apply per bulan" />
    <x-chart-panel name="apply_weekly" title="Apply per minggu" class="lg:col-span-2" />
</div>