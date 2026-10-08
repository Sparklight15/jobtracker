<div class="grid gap-4 lg:grid-cols-2">

    <div class="relative lg:col-span-2">
        <x-chart-panel name="channel_distribution" title="Loker per channel" />

        <x-info-popover
            title="Loker per channel"
            about="Jumlah lamaranmu dari tiap channel, misalnya job portal, referral, atau website perusahaan. Diurutkan dari yang terbanyak."
            how="Makin panjang batangnya, makin banyak lamaran yang kamu kirim lewat channel itu. Lamaran tanpa channel masuk ke 'Tanpa channel'."
            insight="Ini menunjukkan ke mana usahamu paling banyak tersalurkan. Bandingkan dengan dua grafik di bawah untuk tahu apakah channel andalanmu juga yang paling menghasilkan." />
    </div>

    <div class="relative">
        <x-chart-panel name="channel_conversion" title="Conversion per channel" />

        <x-info-popover
            title="Conversion per channel"
            about="Persentase lamaran di tiap channel yang pernah sampai tahap Interview atau Offer."
            how="Makin tinggi batangnya, makin besar peluang lamaran dari channel itu berlanjut. Lamaran yang akhirnya ditolak tapi pernah sampai Interview tetap dihitung. Channel dengan lamaran terlalu sedikit dikosongkan."
            insight="Channel dengan batang tertinggi adalah yang paling efektif untukmu. Pertimbangkan menambah porsi apply di sana dan mengurangi channel yang hampir tidak menghasilkan interview." />
    </div>

    <div class="relative">
        <x-chart-panel name="channel_response_rate" title="Response rate per channel" />

        <x-info-popover
            title="Response rate per channel"
            about="Persentase lamaran di tiap channel yang sudah mendapat respons pertama dari perusahaan."
            how="Dihitung dari lamaran yang tanggal respons pertamanya terisi, dibagi total lamaran channel itu. Channel dengan lamaran terlalu sedikit dikosongkan."
            insight="Response rate rendah berarti lamaranmu sering tidak dilirik atau tenggelam. Coba ganti channel, atau gunakan referral untuk channel yang jarang membalas." />
    </div>

</div>