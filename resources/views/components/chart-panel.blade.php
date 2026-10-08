@props(['name', 'title', 'height' => 'h-64'])

{{-- Panel grafik (8.4). Grafik digambar oleh resources/js/stats/chart-theme.js.
     Wadah [data-chart-views] diisi tombol pilihan rentang (Hari/Minggu/Bulan) oleh
     lazy-load.js, hanya kalau payload grafik punya "views". Grafik lain tidak terpengaruh.
     pr-12 menyisakan ruang untuk tombol info (!) di pojok kanan atas kartu.
     $height = kelas tinggi Tailwind untuk area grafik (bawaan h-64). Tulis kelasnya
     utuh di pemanggil (height="h-80"), jangan dirakit dari potongan string, supaya
     Tailwind ikut membuat kelasnya. --}}
<x-card {{ $attributes->merge(['data-chart-panel' => $name]) }}>
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 pr-12">
        <p class="text-sm font-semibold text-obsidian">{{ $title }}</p>

        <div data-chart-views class="hidden"></div>
    </div>

    <div class="relative mt-4 {{ $height }}">
        <div data-chart-loading class="absolute inset-0 animate-pulse rounded-field bg-nude/60"></div>
        <canvas data-chart-canvas class="hidden"></canvas>

        <div data-chart-empty class="absolute inset-0 hidden flex-col items-center justify-center text-center">
            <p class="font-display text-h2 text-obsidian">Data belum cukup</p>
            <p data-chart-empty-detail class="mt-1 text-sm text-obsidian/70"></p>
        </div>

        <p data-chart-error class="absolute inset-0 hidden items-center justify-center text-sm text-error">Gagal memuat grafik.</p>
    </div>
</x-card>