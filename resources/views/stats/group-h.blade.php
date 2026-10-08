<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <!-- Komparasi durasi pencarian -->
    <x-stat-card name="search_duration_vs_market" label="Durasi Anda vs Pasar" />

    <!-- Informasi statis dari seeder benchmark -->
    <x-stat-card name="market_competition" label="Estimasi Pesaing per Loker" />

    <!-- Peluang offer user vs peluang pasar (1 / pelamar per loker) -->
    <x-stat-card name="offer_chance_vs_market" label="Peluang Offer Anda vs Pasar" />
</div>

<div class="mt-4">
    <!-- Chart komparasi kecepatan hire (Bar horizontal disarankan agar komparasinya jelas) -->
    <div class="relative">
        <x-chart-panel name="time_to_hire_vs_market" title="Kecepatan hingga Offering: Anda vs Pasar" />

        <x-info-popover
            title="Kecepatan hingga Offering: Anda vs Pasar"
            about="Membandingkan rata-rata hari dari kamu melamar sampai mendapat Offer dengan rata-rata pasar."
            how="Batang 'Anda' dihitung dari lamaranmu yang sudah sampai Offer. Batang 'Pasar' adalah angka benchmark. Makin pendek batangnya, makin cepat."
            insight="Kalau batangmu lebih pendek dari pasar, prosesmu lebih cepat dari rata-rata. Kalau lebih panjang, jangan langsung khawatir: angka ini baru kuat kalau offer-mu sudah cukup banyak." />
    </div>
</div>