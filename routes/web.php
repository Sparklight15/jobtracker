<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('beranda'));

// Semua halaman aplikasi: wajib login
Route::middleware('auth')->group(function () {
    Route::view('/beranda', 'beranda')->name('beranda');

    // Route loker, tambah loker, statistik, dan seterusnya ditambahkan di sini
});

// Uji visual komponen, hanya untuk development
if (app()->environment('local')) {
    Route::view('/komponen', 'komponen')->name('komponen');
}

require __DIR__.'/auth.php';