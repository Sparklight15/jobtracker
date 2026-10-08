<div class="grid gap-4 lg:grid-cols-2">

    <div class="relative lg:col-span-2">
        <x-chart-panel name="rejection_reasons" title="Alasan rejection" />

        <x-info-popover
            title="Alasan rejection"
            about="Alasan penolakan dari lamaran yang statusnya sekarang Rejected, diurutkan dari yang terbanyak."
            how="Makin panjang batangnya, makin sering alasan itu muncul. Lamaran ditolak yang alasannya belum diisi masuk ke 'Tidak diisi' di urutan terakhir."
            insight="Alasan yang paling sering muncul adalah pola yang bisa kamu perbaiki lebih dulu. Mengisi alasan tiap penolakan membuat analisis ini makin akurat." />
    </div>

    <div class="relative">
        <x-chart-panel name="rejection_stage" title="Ditolak di tahap mana" />

        <x-info-popover
            title="Ditolak di tahap mana"
            about="Jumlah lamaran Rejected berdasarkan tahap terakhir sebelum ditolak: Applied, Screening, atau Interview."
            how="Tahap diambil dari riwayat status. Perpindahan ke Ghosted dilewati. 'Lainnya' hanya muncul kalau ada kasus yang tahapnya tidak bisa dipastikan."
            insight="Kalau banyak gugur di Applied atau Screening, perbaiki CV dan kecocokan loker. Kalau banyak gugur di Interview, fokus latihan wawancara." />
    </div>

    <div class="relative">
        <x-chart-panel name="offer_decision" title="Keputusan offer" />

        <x-info-popover
            title="Keputusan offer"
            about="Pembagian keputusan untuk lamaran yang statusnya sekarang Offer: diterima, ditolak, atau masih menunggu."
            how="Tiap irisan menunjukkan jumlah offer. Offer yang keputusannya belum diisi dianggap Menunggu."
            insight="Offer yang masih menunggu adalah yang perlu segera kamu putuskan sebelum batas waktunya lewat." />
    </div>

</div>