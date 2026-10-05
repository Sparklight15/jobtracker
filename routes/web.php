<?php

use App\Http\Controllers\JobController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('beranda'));

// Semua halaman aplikasi: wajib login
Route::middleware('auth')->group(function () {
    Route::view('/beranda', 'beranda')->name('beranda');

    // Loker
    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');

    // PENTING: /jobs/create harus didaftarkan SEBELUM route /jobs/{job}
    Route::get('/jobs/create', [JobController::class, 'create'])->name('jobs.create');
    Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');

    Route::get('/jobs/{job}', [JobController::class, 'show'])
        ->whereNumber('job')
        ->name('jobs.show');

    Route::patch('/jobs/{job}/status', [JobController::class, 'updateStatus'])
        ->whereNumber('job')
        ->name('jobs.status');

    Route::delete('/jobs/{job}', [JobController::class, 'destroy'])
        ->whereNumber('job')
        ->name('jobs.destroy');

    Route::get('/jobs/{job}/edit', [JobController::class, 'edit'])
        ->whereNumber('job')
        ->name('jobs.edit');

    Route::put('/jobs/{job}', [JobController::class, 'update'])
        ->whereNumber('job')
        ->name('jobs.update');

    // Route statistik dan seterusnya ditambahkan di sini
});

// Uji visual komponen, hanya untuk development
if (app()->environment('local')) {
    Route::view('/komponen', 'komponen')->name('komponen');
}

require __DIR__.'/auth.php';