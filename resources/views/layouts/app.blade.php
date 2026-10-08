<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts: pakai link font yang sama dengan yang kamu pakai di layout lain -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-offwhite"
             x-data="{
                 micro: window.innerWidth < 1024
                     ? true
                     : (localStorage.getItem('sidebarMicro') === 'true'),

                 // 'terbuka' = kebalikan micro (dipakai lapisan gelap di HP/iPad)
                 get terbuka() { return ! this.micro },
                 set terbuka(nilai) { this.micro = ! nilai },

                 toggle() {
                     this.micro = ! this.micro;
                     if (window.innerWidth >= 1024) {
                         try { localStorage.setItem('sidebarMicro', this.micro) } catch (e) {}
                     }
                 },
             }">

            @include('layouts.navigation')

            {{-- Konten bergeser mengikuti lebar sidebar (di HP/iPad selalu seukuran rail) --}}
            <div class="pl-[4.5rem] transition-[padding] duration-200 ease-out motion-reduce:transition-none"
                 :class="micro ? 'lg:pl-[4.5rem]' : 'lg:pl-64'">

                @if (isset($header))
                    <header class="border-b border-nude bg-white">
                        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>