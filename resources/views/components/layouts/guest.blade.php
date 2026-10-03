@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-auth">
            <p class="mb-6 text-center font-display text-h1 italic">{{ config('app.name') }}</p>

            <div class="rounded-card border border-nude bg-ivory p-6 shadow-card">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>