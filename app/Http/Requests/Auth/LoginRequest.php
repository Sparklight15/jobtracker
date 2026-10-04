<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Batas per kombinasi email + IP */
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 60;

    /** Batas per IP (semua email) */
    private const IP_MAX_ATTEMPTS = 20;
    private const IP_DECAY_SECONDS = 300;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $emailTerdaftar = User::where('email', $this->input('email'))->exists();

        if (! $emailTerdaftar) {
            $this->hitLimiters();

            throw ValidationException::withMessages([
                'email' => trans('auth.email_not_found'),
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $this->hitLimiters();

            throw ValidationException::withMessages([
                'password' => trans('auth.password_wrong'),
            ]);
        }

        // Login berhasil: reset hitungan email, hitungan IP tetap berjalan
        RateLimiter::clear($this->throttleKey());
        session()->forget('throttle_until');
    }

    public function ensureIsNotRateLimited(): void
    {
        $key = null;

        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            $key = $this->throttleKey();
        } elseif (RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::IP_MAX_ATTEMPTS)) {
            $key = $this->ipThrottleKey();
        }

        if ($key === null) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($key);

        // Waktu berakhir disimpan, supaya hitung mundur tetap benar setelah refresh
        session()->put('throttle_until', now()->addSeconds($seconds)->timestamp);

        throw ValidationException::withMessages([
            'throttle' => trans('auth.throttle', ['seconds' => $seconds]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    public function ipThrottleKey(): string
    {
        return 'login-ip|'.$this->ip();
    }

    private function hitLimiters(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
        RateLimiter::hit($this->ipThrottleKey(), self::IP_DECAY_SECONDS);
    }
}