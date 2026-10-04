<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Tampilkan form reset, atau halaman "tautan tidak berlaku".
     */
    public function create(Request $request): View
    {
        $status = $this->tokenStatus(
            (string) $request->route('token'),
            (string) $request->query('email')
        );

        if ($status !== 'valid') {
            return view('auth.reset-link-invalid', [
                'expired' => $status === 'expired',
                'menit' => config('auth.passwords.users.expire'),
            ]);
        }

        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Simpan kata sandi baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        // Token kedaluwarsa atau sudah dipakai: arahkan ke halaman penjelasan
        if ($status === Password::INVALID_TOKEN) {
            return redirect()->route('password.reset', [
                'token' => $request->token,
                'email' => $request->email,
            ]);
        }

        return back()->withInput($request->only('email'))
                     ->withErrors(['email' => __($status)]);
    }

    /**
     * 'valid', 'expired', atau 'invalid' (salah, sudah dipakai, atau tidak ada).
     */
    private function tokenStatus(string $token, string $email): string
    {
        $row = DB::table(config('auth.passwords.users.table'))
            ->where('email', $email)
            ->first();

        if (! $row || ! Hash::check($token, $row->token)) {
            return 'invalid';
        }

        $expire = (int) config('auth.passwords.users.expire');

        if (Carbon::parse($row->created_at)->addMinutes($expire)->isPast()) {
            return 'expired';
        }

        return 'valid';
    }
}