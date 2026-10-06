<?php

return [
    // Periode bawaan Beranda jika query ?period= kosong atau tidak valid
    'default_period' => '90d',

    // Batas minimal sampel (jumlah loker) sebelum angka ditampilkan.
    // Di bawah batas ini kartu/grafik memakai status "insufficient".
    'min_sample' => [
        'percentage' => 5,   // persentase dan conversion
        'average'    => 3,   // rata-rata (durasi, gaji)
        'breakdown'  => 5,   // distribusi per kategori
        'prediction' => 10,  // skor prediksi (#28)
    ],
];
