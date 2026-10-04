<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
{
    ResetPassword::toMailUsing(function (object $notifiable, string $token) {
        $url = url(route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $menit = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Atur ulang kata sandi Job Tracker')
            ->greeting('Halo!')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akunmu.')
            ->action('Atur ulang kata sandi', $url)
            ->line("Tautan ini berlaku selama {$menit} menit.")
            ->line('Kalau bukan kamu yang meminta, abaikan saja email ini. Kata sandimu tidak akan berubah.')
            ->salutation("Salam,\nJob Tracker");
    });
}
}
