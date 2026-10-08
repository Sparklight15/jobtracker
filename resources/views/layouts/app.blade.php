@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Laravel') }}</title>

        {{-- Font (Nunito dan Instrument Serif) dimuat lewat bundle Vite, jadi link Figtree dihapus --}}

        {{--
            Tentukan state sidebar SEBELUM halaman digambar pertama kali,
            supaya sidebar tidak melebar/menyusut setiap pindah halaman.
        --}}
        <script>
            (() => {
                try {
                    const desktop = matchMedia('(min-width: 1024px)').matches;
                    const mikro = localStorage.getItem('sidebar-mikro') === '1';
                    if (! desktop || mikro) document.documentElement.classList.add('sidebar-micro');
                } catch (e) {}
            })();

            // Sembunyikan teks font-display sampai font aslinya siap,
            // supaya judul tidak sempat tampil dengan font cadangan lalu "kedip".
            (() => {
                const h = document.documentElement;
                h.classList.add('fonts-loading');
                const selesai = () => h.classList.remove('fonts-loading');
                setTimeout(selesai, 1500); // jaring pengaman kalau font gagal dimuat
                const tunggu = () => requestAnimationFrame(() => document.fonts.ready.then(selesai));
                if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tunggu);
                else tunggu();
            })();
        </script>
        <style>
            /* Judul/logo menunggu font aslinya, tanpa transisi supaya tidak berkedip */
            html.fonts-loading .font-display { opacity: 0; }

            /* Semua aturan ini hanya aktif saat load awal.
               Hilang otomatis begitu <html> mendapat class "sidebar-siap". */
            html:not(.sidebar-siap) #sidebar,
            html:not(.sidebar-siap) #sidebar *:not(#nav-indicator):not(.nav-link),
            html:not(.sidebar-siap) #konten { transition: none !important; }

            html:not(.sidebar-siap) #sidebar { width: 16rem; max-width: 85vw; }
            html:not(.sidebar-siap).sidebar-micro #sidebar { width: 4.5rem; overflow: hidden; }

            html:not(.sidebar-siap) #konten { padding-left: 4.5rem; }
            @media (min-width: 1024px) {
                html:not(.sidebar-siap):not(.sidebar-micro) #konten { padding-left: 16rem; }
            }

            /* Tampilan micro sebelum Alpine menerapkan class-nya */
            html:not(.sidebar-siap).sidebar-micro .sidebar-row { justify-content: center; padding-inline: 0; }
            html:not(.sidebar-siap).sidebar-micro .sidebar-label { display: none; }
            html:not(.sidebar-siap) .sidebar-tip { display: none; }
        </style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <a href="#isi-utama"
           class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-field focus:bg-obsidian focus:px-4 focus:py-2 focus:text-offwhite">
            Lewati ke konten
        </a>

        {{--
            State sidebar dipegang di sini supaya sidebar DAN konten bisa bereaksi.
            Semua ukuran layar memakai desain yang sama: micro (rail ikon) dan macro (lebar penuh).
            - Desktop (>= 1024px): macro mendorong konten; pilihan `mikro` diingat di localStorage
            - HP/iPad (< 1024px) : default micro; `terbuka` = macro menimpa konten
            `micro` = apakah sidebar sedang tampil sebagai rail ikon saat ini.
        --}}
        <div class="min-h-screen bg-offwhite"
             x-data="{
                 terbuka: false,
                 mikro: (() => { try { return localStorage.getItem('sidebar-mikro') === '1' } catch (e) { return false } })(),
                 desktop: window.matchMedia('(min-width: 1024px)').matches,
                 get micro() { return this.desktop ? this.mikro : ! this.terbuka },
                 init() {
                     window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => { this.desktop = e.matches; this.terbuka = false });
                     // Aktifkan animasi lebar/padding hanya setelah tampilan awal selesai digambar
                     requestAnimationFrame(() => requestAnimationFrame(() => document.documentElement.classList.add('sidebar-siap')));
                 },
                 toggle() {
                     if (this.desktop) {
                         this.mikro = ! this.mikro;
                         try { localStorage.setItem('sidebar-mikro', this.mikro ? '1' : '0') } catch (e) {}
                     } else {
                         this.terbuka = ! this.terbuka;
                     }
                 },
             }"
             x-effect="document.body.classList.toggle('overflow-hidden', terbuka && ! desktop)"
             @keydown.escape.window="terbuka = false">

            @include('layouts.navigation')

            {{-- Area konten: bergeser mengikuti lebar sidebar --}}
            <div id="konten"
                 class="pl-[4.5rem] transition-[padding] duration-200 ease-out motion-reduce:transition-none"
                 :class="desktop && ! mikro ? 'lg:pl-64' : 'lg:pl-[4.5rem]'">

                <!-- Page Heading -->
                @if (isset($header))
                    <header class="border-b border-nude bg-white">
                        <div class="mx-auto max-w-page px-4 py-6 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content: jarak ke sidebar/tepi + lebar maksimal (token max-w-page = 1200px) -->
                <main id="isi-utama" class="mx-auto w-full max-w-page px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>