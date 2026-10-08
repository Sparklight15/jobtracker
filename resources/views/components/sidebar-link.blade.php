{{--
    Link menu sidebar. Otomatis menyesuaikan mode micro (hanya ikon + tooltip)
    karena membaca state `micro` dari x-data di layouts/app.blade.php.

    Kotak hitam penanda menu aktif BUKAN bagian dari link ini, melainkan
    elemen #nav-indicator di navigation.blade.php yang digeser lewat JS.
    Link aktif hanya memberi atribut data-active sebagai penanda posisi.
--}}
@props(['active' => false, 'icon'])

<a {{ $attributes->merge(['class' => 'nav-link sidebar-row group relative flex h-10 items-center gap-3 rounded-field text-sm font-semibold transition-colors duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-obsidian focus-visible:ring-offset-2 focus-visible:ring-offset-ivory ' . ($active ? 'text-obsidian' : 'text-obsidian/70 hover:bg-nude/60 hover:text-obsidian')]) }}
   @if ($active) aria-current="page" data-active @endif
   :class="micro ? 'justify-center px-0' : 'px-3'">

    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
    </svg>

    <span class="sidebar-label truncate" :class="{ 'sr-only': micro }">{{ $slot }}</span>

    {{-- Tooltip: hanya muncul di desktop saat mode micro --}}
    <span class="sidebar-tip pointer-events-none absolute left-full top-1/2 z-50 ml-3 -translate-y-1/2 whitespace-nowrap rounded-field bg-obsidian px-2.5 py-1.5 text-xs font-semibold text-offwhite opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100"
          :class="micro ? 'block' : 'hidden'"
          aria-hidden="true">{{ $slot }}</span>
</a>