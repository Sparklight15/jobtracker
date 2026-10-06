<div class="grid gap-4 sm:grid-cols-2">
    <!-- Komparasi durasi pencarian -->
    <x-stat-card name="search_duration_vs_market" label="Durasi Anda vs Pasar" />
    
    <!-- Informasi statis dari seeder benchmark -->
    <x-stat-card name="market_competition" label="Estimasi Pesaing per Loker" />
</div>

<div class="mt-4">
    <!-- Chart komparasi kecepatan hire (Bar horizontal disarankan agar komparasinya jelas) -->
    <x-chart-panel name="time_to_hire_vs_market" title="Kecepatan hingga Offering: Anda vs Pasar" />
</div>