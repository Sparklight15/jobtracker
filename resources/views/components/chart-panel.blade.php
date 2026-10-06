@props(['name', 'title'])

{{-- Panel grafik (8.4). Grafik digambar oleh resources/js/stats/chart-theme.js --}}
<x-card {{ $attributes->merge(['data-chart-panel' => $name]) }}>
    <p class="text-sm font-semibold text-obsidian">{{ $title }}</p>

    <div class="relative mt-4 h-64">
        <div data-chart-loading class="absolute inset-0 animate-pulse rounded-field bg-nude/60"></div>
        <canvas data-chart-canvas class="hidden"></canvas>

        <div data-chart-empty class="absolute inset-0 hidden flex-col items-center justify-center text-center">
            <p class="font-display text-h2 text-obsidian">Data belum cukup</p>
            <p data-chart-empty-detail class="mt-1 text-sm text-obsidian/70"></p>
        </div>

        <p data-chart-error class="absolute inset-0 hidden items-center justify-center text-sm text-error">Gagal memuat grafik.</p>
    </div>
</x-card>