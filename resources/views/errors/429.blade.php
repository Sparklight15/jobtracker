@php
    $detik = (int) ($exception->getHeaders()['Retry-After'] ?? 0);
    $menit = (int) ceil($detik / 60);
@endphp

<x-layouts.guest title="Terlalu Banyak Permintaan">
    <h1 class="text-center font-display text-h1 italic">Terlalu banyak permintaan</h1>
    <p class="mt-2 text-center text-sm text-obsidian/70">
        Kamu terlalu sering mengirim permintaan.
        @if ($detik > 0)
            Coba lagi dalam {{ $detik > 90 ? $menit.' menit' : $detik.' detik' }}.
        @else
            Tunggu beberapa saat lalu coba lagi.
        @endif
    </p>

    <div class="mt-6">
        <x-button :href="route('login')" full>Kembali ke halaman masuk</x-button>
    </div>
</x-layouts.guest>