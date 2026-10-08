@props(['name', 'label'])

{{-- Kartu angka (8.4). Diisi oleh resources/js/stats/lazy-load.js lewat atribut data-stat-*.
     Susunan: label (penuh) -> angka kiri + sparkline kanan -> catatan (penuh).
     Sparkline muncul hanya kalau backend mengirim "spark" untuk kartu ini. --}}
<x-card {{ $attributes->merge(['data-stat-card' => $name]) }}>
    <p class="text-sm text-obsidian/70">{{ $label }}</p>

    <div class="mt-2 flex items-end justify-between gap-4">
        <div class="min-w-0 shrink-0">
            <div data-stat-loading class="h-12 w-32 animate-pulse rounded-field bg-nude/60"></div>

            <p data-stat-value class="hidden font-display text-stat text-obsidian"></p>

            <div data-stat-empty class="hidden">
                <p class="font-display text-h2 text-obsidian">Data belum cukup</p>
                <p data-stat-empty-detail class="mt-1 text-sm text-obsidian/70"></p>
            </div>

            <p data-stat-error class="hidden text-sm text-error">Gagal memuat data.</p>
        </div>

        {{-- Sparkline: dekoratif (angkanya sudah ada di teks), jadi disembunyikan dari pembaca layar.
             Lebarnya fleksibel (maks 8rem) supaya tidak mendesak angka di kartu yang sempit. --}}
        <div data-stat-spark-wrap class="relative hidden h-12 min-w-0 max-w-[8rem] flex-1" aria-hidden="true">
            <canvas data-stat-spark></canvas>
        </div>
    </div>

    <p data-stat-note class="mt-3 hidden text-sm text-obsidian/70"></p>
</x-card>