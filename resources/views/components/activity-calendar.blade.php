@props(['name', 'title'])

{{-- Kalender aktivitas (grup B). Berbeda dengan x-chart-panel, isinya HTML biasa (bukan canvas),
     digambar oleh resources/js/stats/activity-calendar.js ke dalam [data-calendar-body].
     Atribut data-chart-panel + elemen loading/empty/error dipertahankan supaya lazy-load.js
     memperlakukannya sama seperti grafik lain.
     pr-12 menyisakan ruang untuk tombol info (!) di pojok kanan atas kartu. --}}
<x-card {{ $attributes->merge(['data-chart-panel' => $name]) }}>
    <div class="pr-12">
        <p class="text-sm font-semibold text-obsidian">{{ $title }}</p>
    </div>

    <div class="relative mt-4 min-h-64">
        <div data-chart-loading class="absolute inset-0 animate-pulse rounded-field bg-nude/60"></div>

        <div data-calendar-body class="hidden"></div>

        <div data-chart-empty class="absolute inset-0 hidden flex-col items-center justify-center text-center">
            <p class="font-display text-h2 text-obsidian">Data belum cukup</p>
            <p data-chart-empty-detail class="mt-1 text-sm text-obsidian/70"></p>
        </div>

        <p data-chart-error class="absolute inset-0 hidden items-center justify-center text-sm text-error">Gagal memuat kalender.</p>
    </div>
</x-card>