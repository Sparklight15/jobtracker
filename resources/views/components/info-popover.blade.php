@props(['title', 'about' => '', 'how' => '', 'insight' => ''])

{{-- Tombol info (!) di pojok kanan atas kartu. Klik membuka popover ringkas dengan segitiga penunjuk.
     Ditutup lewat klik di luar, tombol Esc, atau klik tombol (!) lagi.
     Induknya harus relative: lapisan ini menutupi seluruh kartu (tanpa menangkap klik)
     supaya ukuran popover dibatasi oleh kartu. Kalau isinya lebih tinggi dari kartu, bagian dalamnya bisa di-scroll. --}}
<div x-data="{ open: false }"
     @keydown.escape.window="open = false"
     @click.outside="open = false"
     {{ $attributes->class(['pointer-events-none absolute inset-0 z-20']) }}>

    <button type="button"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-label="Penjelasan: {{ $title }}"
            class="pointer-events-auto absolute right-5 top-5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-offwhite text-obsidian/60 shadow-sm ring-1 ring-nude transition-colors hover:bg-nude/50 hover:text-obsidian"
            :class="open && '!bg-obsidian !text-offwhite !ring-obsidian'">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/>
        </svg>
    </button>

    {{-- Pembungkus: menahan tinggi maksimal sebesar kartu. Segitiga ada di sini (di luar area scroll) supaya tidak terpotong. --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition duration-150 ease-out"
         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition duration-100 ease-in"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         role="dialog"
         aria-label="{{ $title }}"
         class="pointer-events-auto absolute right-4 top-[3.75rem] flex max-h-[calc(100%-4.5rem)] w-64 max-w-[calc(100%-2rem)] origin-top-right flex-col sm:w-72">

        {{-- Segitiga penunjuk ke tombol (!) --}}
        <span class="absolute -top-1.5 right-[0.875rem] h-3 w-3 rotate-45 rounded-[2px] bg-offwhite ring-1 ring-obsidian/10" aria-hidden="true"></span>

        <div class="relative min-h-0 flex-1 overflow-y-auto overscroll-contain rounded-2xl bg-offwhite p-4 text-left shadow-[0_12px_40px_-8px_rgba(16,16,16,0.22)] ring-1 ring-obsidian/10 [scrollbar-width:thin]">

            <h3 class="font-sans text-sm font-bold not-italic leading-snug text-obsidian">{{ $title }}</h3>

            @if ($about)
                <p class="mt-1 text-sm leading-snug text-obsidian/70">{{ $about }}</p>
            @endif

            @if ($insight)
                <div class="mt-3 rounded-xl bg-ivory p-3">
                    <p class="flex items-center gap-1.5 text-xs font-semibold text-obsidian/60">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2V16h5v-.1c0-.8.4-1.5 1-2A6 6 0 0 0 12 3Z"/>
                        </svg>
                        Insight
                    </p>
                    <p class="mt-1 text-sm font-medium leading-snug text-obsidian">{{ $insight }}</p>
                </div>
            @endif

            @if ($how)
                <p class="mt-3 text-xs leading-snug text-obsidian/55">
                    <span class="font-semibold text-obsidian/70">Cara baca:</span> {{ $how }}
                </p>
            @endif
        </div>
    </div>
</div>