<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="mx-auto max-w-page px-4 py-8 lg:px-12">
    <h1>Job Tracker</h1>
    <p class="mt-1 text-obsidian/70">Pantau semua lamaran kerjamu di satu tempat.</p>

    <h2 class="mt-8">Statistik</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-card border border-nude bg-ivory p-6 shadow-card">
            <p class="font-display text-stat italic">24</p>
            <p class="mt-2 text-xs text-obsidian/60">Total lamaran</p>
        </div>
    </div>

    <div class="mt-8 max-w-auth rounded-card border border-nude bg-ivory p-6 shadow-card">
        <label class="font-semibold">Email</label>
        <input type="email" placeholder="nama@email.com"
               class="mt-1 w-full rounded-field border border-nude bg-offwhite px-3 py-2 focus:border-obsidian focus:outline-none focus:ring-1 focus:ring-obsidian">
        <p class="mt-1 text-xs text-error">Contoh teks error</p>

        <div class="mt-4 flex gap-2">
            <button class="h-btn rounded-field bg-obsidian px-4 font-semibold text-offwhite hover:opacity-90">Simpan</button>
            <button class="h-btn rounded-field border border-obsidian px-4 font-semibold hover:bg-ivory">Batal</button>
        </div>
    </div>

    <div class="mt-8 flex flex-wrap gap-2 text-xs font-semibold">
        <span class="rounded-full border border-dashed border-nude px-3 py-1 text-obsidian/70">Applied</span>
        <span class="rounded-full border border-obsidian px-3 py-1">Interview</span>
        <span class="rounded-full bg-obsidian px-3 py-1 text-offwhite">Offer</span>
        <span class="rounded-full bg-nude px-3 py-1 text-obsidian/60">Rejected</span>
    </div>
</main>
</body>
</html>