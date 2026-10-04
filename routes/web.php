<?php

use App\Http\Controllers\JobController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('beranda'));

// Semua halaman aplikasi: wajib login
Route::middleware('auth')->group(function () {
    Route::view('/beranda', 'beranda')->name('beranda');

    // Loker
    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');

    // PENTING: nanti /jobs/create harus didaftarkan SEBELUM route /jobs/{job}
    Route::get('/jobs/{job}', [JobController::class, 'show'])
        ->whereNumber('job')
        ->name('jobs.show');

    Route::patch('/jobs/{job}/status', [JobController::class, 'updateStatus'])
        ->whereNumber('job')
        ->name('jobs.status');

    Route::delete('/jobs/{job}', [JobController::class, 'destroy'])
        ->whereNumber('job')
        ->name('jobs.destroy');

    // Route edit, statistik, dan seterusnya ditambahkan di sini
});

// Uji visual komponen, hanya untuk development
if (app()->environment('local')) {
    Route::view('/komponen', 'komponen')->name('komponen');
}

require __DIR__.'/auth.php';