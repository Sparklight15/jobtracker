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

    // Daftar grup untuk menu anchor dan judul section Beranda (8.2)
    'groups' => [
        'A' => ['title' => 'Funnel lamaran',        'description' => 'Total apply, jumlah per tahap, conversion, dan rasio ghosting.'],
        'B' => ['title' => 'Waktu dan tren',        'description' => 'Waktu respons, durasi tiap tahap, durasi pencarian, dan tren apply.'],
        'C' => ['title' => 'Channel',               'description' => 'Distribusi, conversion, dan response rate per channel.'],
        'D' => ['title' => 'Sektor industri',       'description' => 'Distribusi, conversion, dan rata-rata gaji per sektor.'],
        'E' => ['title' => 'Lokasi dan mode kerja', 'description' => 'Distribusi kota dan conversion per mode kerja.'],
        'F' => ['title' => 'Kualitas lamaran',      'description' => 'Fit score, CV tailored vs generic, skill gap, referral vs cold apply.'],
        'G' => ['title' => 'Hasil akhir',           'description' => 'Alasan rejection dan keputusan offer.'],
        'H' => ['title' => 'Benchmark',             'description' => 'Perbandingan dengan rata-rata pasar.'],
        'I' => ['title' => 'Prediksi dan tren',     'description' => 'Skor prediksi dan tren funnel gabungan per minggu.'],
    ],
];