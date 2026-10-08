<div class="grid gap-4 lg:grid-cols-2">

    <div class="relative lg:col-span-2">
        <x-chart-panel name="sector_distribution" title="Loker per sektor" />

        <x-info-popover
            title="Loker per sektor"
            about="Jumlah lamaranmu di tiap sektor industri, diurutkan dari yang terbanyak."
            how="Makin panjang batangnya, makin banyak lamaran di sektor itu. Lamaran yang sektornya belum diisi tidak dihitung."
            insight="Lihat apakah lamaranmu terlalu menumpuk di satu sektor. Kalau iya, kamu mungkin melewatkan peluang di sektor lain yang cocok dengan skill-mu." />
    </div>

    <div class="relative">
        <x-chart-panel name="sector_conversion" title="Conversion per sektor" />

        <x-info-popover
            title="Conversion per sektor"
            about="Persentase lamaran di tiap sektor yang pernah sampai tahap Interview atau Offer."
            how="Makin tinggi batangnya, makin besar peluang lamaran di sektor itu berlanjut. Sektor dengan lamaran terlalu sedikit dikosongkan."
            insight="Sektor dengan batang tertinggi adalah yang paling menerima profilmu. Arahkan lebih banyak lamaran ke sana." />
    </div>

    <div class="relative">
        <x-chart-panel name="sector_salary" title="Rata-rata gaji ditawarkan per sektor" />

        <x-info-popover
            title="Rata-rata gaji ditawarkan per sektor"
            about="Rata-rata gaji yang benar-benar ditawarkan perusahaan kepadamu, dikelompokkan per sektor."
            how="Hanya lamaran yang sampai tahap Offer dan gajinya terisi yang dihitung, jadi sampelnya biasanya kecil. Sektor dengan data terlalu sedikit dikosongkan."
            insight="Gunakan sebagai pegangan saat negosiasi. Sektor dengan tawaran lebih tinggi bisa jadi prioritas, tapi ingat angka ini hanya dari beberapa offer." />
    </div>

</div>