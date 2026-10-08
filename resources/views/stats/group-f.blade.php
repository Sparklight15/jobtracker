<div class="grid gap-4 lg:grid-cols-2">

    <div class="relative">
        <x-chart-panel name="fit_score_conversion" title="Conversion per fit score (1-5)" />

        <x-info-popover
            title="Conversion per fit score (1-5)"
            about="Persentase lamaran yang pernah sampai Interview atau Offer, dikelompokkan berdasarkan fit score yang kamu isi saat melamar."
            how="Batang tiap skor 1 sampai 5 menunjukkan peluang lolos. Idealnya makin tinggi skor, makin tinggi batangnya. Skor dengan lamaran terlalu sedikit dikosongkan."
            insight="Kalau skor tinggi memang lebih sering lolos, penilaian fit-mu akurat dan bisa dipakai untuk memilih loker. Kalau polanya acak, cara menilai fit perlu dievaluasi." />
    </div>

    <div class="relative">
        <x-chart-panel name="cv_conversion" title="CV disesuaikan vs generik" />

        <x-info-popover
            title="CV disesuaikan vs generik"
            about="Membandingkan persentase lolos ke Interview atau Offer antara lamaran dengan CV yang disesuaikan dan CV generik."
            how="Bandingkan tinggi kedua batang. Lamaran yang jenis CV-nya belum diisi tidak masuk kelompok mana pun."
            insight="Kalau CV yang disesuaikan jelas lebih tinggi, usaha ekstra menyesuaikan CV terbukti berguna. Kalau selisihnya kecil, kamu bisa hemat waktu untuk hal lain." />
    </div>

    <div class="relative">
        <x-chart-panel name="skill_gaps" title="Skill yang paling sering kurang" />

        <x-info-popover
            title="Skill yang paling sering kurang"
            about="Skill yang paling sering kamu catat belum dimiliki saat melamar. Ditampilkan 10 teratas."
            how="Makin panjang batangnya, makin banyak lamaran yang mensyaratkan skill itu. Satu skill dihitung sekali per lamaran, dan penulisannya dinormalkan."
            insight="Skill di urutan teratas adalah prioritas belajarmu. Menutup satu skill ini bisa membuka banyak lamaran sekaligus." />
    </div>

    <div class="relative">
        <x-chart-panel name="referral_conversion" title="Dengan referral vs tanpa referral" />

        <x-info-popover
            title="Dengan referral vs tanpa referral"
            about="Membandingkan persentase lolos ke Interview atau Offer antara lamaran yang punya referral dan yang tidak."
            how="Bandingkan tinggi kedua batang. Referral dihitung dari penanda referral di lamaran, apa pun channel yang dipakai."
            insight="Kalau lamaran dengan referral jauh lebih tinggi, luangkan waktu untuk membangun koneksi dan minta referral sebelum apply." />
    </div>

</div>