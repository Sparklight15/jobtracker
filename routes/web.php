<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('beranda'));

Route::middleware('auth')->group(function () {
    Route::view('/beranda', 'beranda')->name('beranda');
});

// Uji visual komponen, hanya untuk development
if (app()->environment('local')) {
    Route::view('/komponen', 'komponen')->name('komponen');
}

require __DIR__.'/auth.php';