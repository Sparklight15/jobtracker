<?php

use App\Http\Controllers\JobController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('beranda'));

// Semua halaman aplikasi: wajib login
Route::middleware('auth')->group(function () {
    // Beranda dan statistik
    Route::get('/beranda', [StatsController::class, 'index'])->name('beranda');

    Route::get('/stats/{group}', [StatsController::class, 'show'])
        ->where('group', '[A-Ia-i]')
        ->name('stats.show');

    // Profil Pengguna (Fase 9)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Kirim link reset kata sandi ke email akun yang sedang login
    Route::post('/profile/password-reset-link', [ProfileController::class, 'sendPasswordResetLink'])
        ->middleware('throttle:3,1')
        ->name('profile.password-reset-link');

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
});

// Uji visual komponen, hanya untuk development
if (app()->environment('local')) {
    Route::view('/komponen', 'komponen')->name('komponen');
}

require __DIR__.'/auth.php';