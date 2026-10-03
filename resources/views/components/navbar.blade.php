@php
    $links = [
        ['label' => 'Beranda', 'href' => route('beranda'), 'active' => request()->routeIs('beranda')],
        ['label' => 'Loker', 'href' => '#', 'active' => false],
        ['label' => 'Tambah Loker', 'href' => '#', 'active' => false],
    ];
@endphp

<header x-data="{ open: false }" class="border-b border-nude bg-offwhite">
    <div class="mx-auto flex h-16 max-w-page items-center justify-between px-4 sm:px-6 lg:px-12">
        <a href="{{ route('beranda') }}" class="font-display text-h2 italic">
            {{ config('app.name') }}
        </a>

        {{-- Menu horizontal (layar >= 640px) --}}
        <nav class="hidden items-center gap-6 sm:flex" aria-label="Menu utama">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}"
                   class="font-semibold underline-offset-4 hover:text-obsidian hover:underline
                          {{ $link['active'] ? 'text-obsidian underline' : 'text-obsidian/70' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
            {{-- Dihubungkan ke logout saat step autentikasi --}}
            <form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="ISI_DENGAN_CLASS_LINK_NAVBAR_LAINNYA">Keluar</button>
</form>
        </nav>

        {{-- Tombol hamburger (layar < 640px) --}}
        <button type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-field border border-nude hover:bg-ivory focus:outline-none focus:ring-1 focus:ring-obsidian sm:hidden"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-controls="menu-mobile"
                aria-label="Buka atau tutup menu">
            <svg x-show="!open" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
            </svg>
            <svg x-show="open" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>
    </div>

    {{-- Menu mobile --}}
    <nav id="menu-mobile" x-show="open" x-cloak x-transition.opacity
         class="border-t border-nude bg-offwhite px-4 py-2 sm:hidden" aria-label="Menu mobile">
        @foreach ($links as $link)
            <a href="{{ $link['href'] }}"
               class="block rounded-field px-2 py-3 font-semibold hover:bg-ivory
                      {{ $link['active'] ? 'text-obsidian' : 'text-obsidian/70' }}">
                {{ $link['label'] }}
            </a>
        @endforeach
        <form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="ISI_DENGAN_CLASS_LINK_NAVBAR_LAINNYA">Keluar</button>
</form>
    </nav>
</header>