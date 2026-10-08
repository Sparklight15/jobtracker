<div class="grid gap-4 lg:grid-cols-2">

    <div class="relative">
        <x-chart-panel name="city_distribution" title="Loker per kota" />

        <x-info-popover
            title="Loker per kota"
            about="Jumlah lamaranmu di tiap kota. Ditampilkan 5 kota teratas, sisanya digabung jadi 'Lainnya'."
            how="Makin panjang batangnya, makin banyak lamaran di kota itu. Penulisan kota dinormalkan, jadi 'jakarta' dan 'Jakarta' dihitung satu. Lamaran tanpa kota tidak dihitung."
            insight="Cek apakah sebaran lokasimu sesuai rencana. Kalau terlalu terpusat di satu kota, pertimbangkan membuka opsi kota lain atau kerja remote." />
    </div>

    <div class="relative">
        <x-chart-panel name="work_mode_conversion" title="Conversion per mode kerja" />

        <x-info-popover
            title="Conversion per mode kerja"
            about="Persentase lamaran yang pernah sampai tahap Interview atau Offer, dibandingkan antara Remote, Hybrid, dan Onsite."
            how="Makin tinggi batangnya, makin besar peluang lolos di mode kerja itu. Mode dengan lamaran terlalu sedikit dikosongkan."
            insight="Mode dengan batang tertinggi adalah yang paling banyak merespons profilmu. Kalau Remote rendah, wajar: persaingannya biasanya lebih ketat." />
    </div>

</div>