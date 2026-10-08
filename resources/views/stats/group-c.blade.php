<div class="grid gap-4">

    <div class="relative">
        <x-chart-panel name="channel_distribution" title="Loker per channel" />

        <x-info-popover
            title="Loker per channel"
            about="Jumlah lamaranmu dari tiap channel, misalnya job portal, referral, atau website perusahaan. Diurutkan dari yang terbanyak."
            how="Makin besar irisannya, makin banyak lamaran yang kamu kirim lewat channel itu. Arahkan kursor ke irisan atau ke daftar di sampingnya untuk melihat jumlah dan persentasenya. Lamaran tanpa channel masuk ke 'Tanpa channel'."
            insight="Ini menunjukkan ke mana usahamu paling banyak tersalurkan. Bandingkan dengan grafik di bawah untuk tahu apakah channel andalanmu juga yang paling menghasilkan." />
    </div>

    <div class="relative">
        <x-chart-panel name="channel_rates" title="Conversion dan response rate per channel" height="h-80" />

        <x-info-popover
            title="Conversion dan response rate"
            about="Dua garis per channel. Conversion adalah persentase lamaran yang pernah sampai tahap Interview atau Offer. Response rate adalah persentase lamaran yang sudah mendapat respons pertama dari perusahaan."
            how="Arahkan kursor atau ketuk satu channel untuk melihat kedua angkanya beserta jumlah lamarannya, misalnya '3 dari 12'. Lamaran yang akhirnya ditolak tapi pernah sampai Interview tetap dihitung di Conversion. Channel dengan lamaran terlalu sedikit dikosongkan, jadi garisnya terputus di situ."
            insight="Garis hanya menghubungkan titik supaya lebih mudah dibandingkan, bukan menunjukkan tren antar channel. Channel dengan Conversion tertinggi paling efektif untukmu. Kalau Response rate rendah, lamaranmu sering tidak dilirik, coba ganti channel atau gunakan referral." />
    </div>

</div>