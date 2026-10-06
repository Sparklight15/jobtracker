<?php

namespace App\Http\Controllers;

use App\Enums\ApplyTargetPeriod;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Tampilkan halaman profil.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'periodOptions' => ApplyTargetPeriod::options(),
        ]);
    }

    /**
     * Simpan nama dan target lamaran.
     * Email tidak bisa diubah: tidak ada di aturan validasi, jadi tidak ikut validated().
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Kirim link reset kata sandi ke email akun yang sedang login.
     * Email diambil dari akun, bukan dari input pengguna.
     */
    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $request->user()->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return Redirect::route('profile.edit')->with('status', 'password-reset-link-sent');
        }

        $pesan = $status === Password::RESET_THROTTLED
            ? 'Tunggu sebentar sebelum meminta link lagi.'
            : 'Link reset tidak dapat dikirim. Coba lagi nanti.';

        return Redirect::route('profile.edit')
            ->withErrors(['password_reset' => $pesan], 'passwordReset');
    }

    /**
     * Hapus akun beserta seluruh datanya.
     * Tabel jobs, status_history, dan job_skill_gaps ikut terhapus lewat cascadeOnDelete.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ], [
            'password.required' => 'Masukkan kata sandi untuk konfirmasi.',
            'password.current_password' => 'Kata sandi salah.',
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::route('login')->with('status', 'Akunmu sudah dihapus.');
    }
}