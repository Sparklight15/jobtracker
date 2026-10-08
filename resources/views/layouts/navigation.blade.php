@php
    // Cari nama rute beranda yang benar-benar ada. Kalau namanya lain,
    // tambahkan di daftar ini (cek dengan: php artisan route:list).
    $ruteBeranda = collect(['beranda', 'dashboard', 'home'])->first(fn ($nama) => Route::has($nama));
    $urlBeranda  = $ruteBeranda ? route($ruteBeranda) : url('/');

    // ------------------------------------------------------------------
    // Daftar menu utama. Ubah di sini kalau nama rute atau menunya berbeda.
    // Menu yang rutenya belum ada otomatis dilewati (tidak error).
    // 'icon' = path SVG outline 24x24 (Heroicons).
    // ------------------------------------------------------------------
    $menuUtama = [
        [
            'rute'  => $ruteBeranda,
            'cocok' => $ruteBeranda,
            'label' => 'Beranda',
            'icon'  => 'M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
        ],
        [
            'rute'  => 'jobs.index',
            'cocok' => 'jobs.*',
            'label' => 'Daftar Lamaran', // DIUBAH (sebelumnya 'Lamaran')
            'icon'  => 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z',
        ],
        [
            'rute'  => 'stats',
            'cocok' => 'stats*',
            'label' => 'Statistik',
            'icon'  => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        ],
    ];

    $pengguna = Auth::user();
    $fotoUrl  = $pengguna->profile_photo_path ? asset('storage/' . $pengguna->profile_photo_path) : null;
    $inisial  = mb_strtoupper(mb_substr($pengguna->name, 0, 1));
@endphp

{{-- State (terbuka, mikro, micro, toggle) datang dari x-data di layouts/app.blade.php --}}

{{-- Lapisan gelap di belakang sidebar macro (HP/iPad) --}}
<div x-show="terbuka"
     x-transition:enter="transition-opacity ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="terbuka = false"
     class="fixed inset-0 z-40 bg-obsidian/40 lg:hidden"
     style="display: none;"
     aria-hidden="true"></div>

{{-- Sidebar: selalu tampil di kiri. Micro (rail ikon) atau macro (lebar penuh).
     Desktop: mendorong konten. HP/iPad: macro menimpa konten dengan latar gelap. --}}
<aside id="sidebar"
       :class="micro ? 'w-[4.5rem]' : 'w-64 max-w-[85vw]'"
       class="fixed inset-y-0 left-0 z-50 flex flex-col border-r border-nude bg-ivory transition-[width] duration-200 ease-out motion-reduce:transition-none"
       aria-label="Navigasi utama">

    {{-- Tombol ciutkan/lebarkan, menempel di tepi sidebar (semua ukuran layar) --}}
    <button type="button"
            @click="toggle()"
            class="absolute -right-3.5 top-4 z-10 flex h-7 w-7 items-center justify-center rounded-full border border-nude bg-offwhite text-obsidian shadow-sm transition-colors hover:bg-nude focus:outline-none focus-visible:ring-2 focus-visible:ring-obsidian"
            :aria-label="micro ? 'Lebarkan sidebar' : 'Ciutkan sidebar'"
            :aria-expanded="(! micro).toString()"
            aria-controls="sidebar">
        <svg class="h-3.5 w-3.5 transition-transform duration-200 motion-reduce:transition-none" :class="{ 'rotate-180': micro }"
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
    </button>

    {{-- Logo: monogram saja saat micro, monogram + nama saat macro --}}
    <div class="sidebar-row relative flex h-16 shrink-0 items-center gap-3" :class="micro ? 'justify-center px-0' : 'px-5'">
        <a href="{{ $urlBeranda }}" class="flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-obsidian focus-visible:ring-offset-2 focus-visible:ring-offset-ivory rounded-field"
           aria-label="{{ config('app.name', 'JobTracker') }}, ke beranda">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-field bg-obsidian font-display text-xl italic leading-none text-offwhite" aria-hidden="true">
                {{ mb_strtoupper(mb_substr(config('app.name', 'JobTracker'), 0, 1)) }}
            </span>
            <span class="sidebar-label font-display text-2xl italic leading-none text-obsidian" :class="{ 'hidden': micro }">
                {{ config('app.name', 'JobTracker') }}
            </span>
        </a>
    </div>

    {{-- Menu utama (overflow dilepas saat micro supaya tooltip tidak terpotong) --}}
    <nav id="sidebar-nav" class="relative flex-1 space-y-1 px-3 py-2" :class="micro ? 'overflow-visible' : 'overflow-y-auto'">

        {{-- Kotak hitam penanda menu aktif. Digeser lewat resources/js/sidebar-nav.js --}}
        <span id="nav-indicator" aria-hidden="true"
              class="pointer-events-none absolute left-3 right-3 top-0 h-10 rounded-field bg-obsidian opacity-0"></span>

        @foreach ($menuUtama as $menu)
            @if ($menu['rute'] && Route::has($menu['rute']))
                <x-sidebar-link :href="route($menu['rute'])"
                                :active="request()->routeIs($menu['cocok'])"
                                :icon="$menu['icon']">
                    {{ $menu['label'] }}
                </x-sidebar-link>
            @endif
        @endforeach
    </nav>

    {{-- Akun pengguna di bagian bawah --}}
    <div class="shrink-0 space-y-1 border-t border-nude p-3">
        <a id="nav-profil" href="{{ route('profile.edit') }}"
           @if (request()->routeIs('profile.*')) aria-current="page" @endif
           aria-label="Buka profil akun {{ $pengguna->name }}"
           :class="micro ? 'justify-center px-0' : 'px-3'"
           class="sidebar-row group relative flex items-center gap-3 rounded-field py-2 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-obsidian focus-visible:ring-offset-2 focus-visible:ring-offset-ivory {{ request()->routeIs('profile.*') ? 'bg-nude' : 'hover:bg-nude/60' }}">
            @if ($fotoUrl)
                <img src="{{ $fotoUrl }}" alt="" class="h-9 w-9 shrink-0 rounded-full border border-nude object-cover">
            @else
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-nude text-sm font-bold text-obsidian"
                      aria-hidden="true">{{ $inisial }}</span>
            @endif

            <div class="sidebar-label min-w-0" :class="{ 'hidden': micro }">
                <p class="truncate text-sm font-bold text-obsidian">{{ $pengguna->name }}</p>
                <p class="truncate text-xs text-obsidian/60">{{ $pengguna->email }}</p>
            </div>

            <span class="sidebar-tip pointer-events-none absolute bottom-1 left-full z-50 ml-3 whitespace-nowrap rounded-field bg-obsidian px-2.5 py-1.5 text-xs font-semibold text-offwhite opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100"
                  :class="micro ? 'block' : 'hidden'" aria-hidden="true">Profil: {{ $pengguna->name }}</span>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    :class="micro ? 'justify-center px-0' : 'px-3'"
                    class="sidebar-row group relative flex h-10 w-full items-center gap-3 rounded-field text-sm font-semibold text-obsidian/70 transition-colors duration-150 hover:bg-nude/60 hover:text-obsidian focus:outline-none focus-visible:ring-2 focus-visible:ring-obsidian focus-visible:ring-offset-2 focus-visible:ring-offset-ivory">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
                <span class="sidebar-label" :class="{ 'sr-only': micro }">Keluar</span>

                <span class="sidebar-tip pointer-events-none absolute left-full top-1/2 z-50 ml-3 -translate-y-1/2 whitespace-nowrap rounded-field bg-obsidian px-2.5 py-1.5 text-xs font-semibold text-offwhite opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100"
                      :class="micro ? 'block' : 'hidden'" aria-hidden="true">Keluar</span>
            </button>
        </form>
    </div>
</aside>

{{-- Kotak hitam digeser oleh resources/js/sidebar-nav.js.
     Daftar kelas di bawah ini supaya Tailwind ikut membuatnya (dipakai dari JS):
     !text-offwhite text-obsidian/70 hover:bg-nude/60 hover:text-obsidian bg-nude --}}