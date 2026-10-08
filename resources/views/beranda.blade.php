<x-layouts.app title="Beranda">
    @php
        // Ikon per grup (garis 1.5, grid 24x24, senada ikon sidebar). Grup tak dikenal memakai ikon cadangan.
        $icons = [
            'A' => '<path d="M3 5h18l-7 8v5l-4 2v-7L3 5Z"/>',                                                                            // funnel
            'B' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',                                                             // jam
            'C' => '<circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.2 10.8l7.6-3.6M8.2 13.2l7.6 3.6"/>', // channel
            'D' => '<path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16M14 9h5a1 1 0 0 1 1 1v11M2 21h20M8 8h2M8 12h2M8 16h2"/>',          // gedung
            'E' => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',                      // pin lokasi
            'F' => '<path d="M12 3.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8L12 16.9l-5.3 2.7 1-5.8-4.2-4.1 5.9-.9L12 3.5Z"/>',                      // bintang
            'G' => '<path d="M5 21V4M5 4h12l-2 4 2 4H5"/>',                                                                              // bendera
            'H' => '<path d="M3 21h18M6 21v-8M12 21V4M18 21v-12"/>',                                                                     // bar
            'I' => '<path d="M3 17l6-6 4 4 8-8M15 7h6v6"/>',                                                                             // tren naik
        ];
        $iconCadangan = '<circle cx="12" cy="12" r="3"/>';
    @endphp

    <div data-stats-root>
        <h1 class="sr-only">Beranda</h1>

        {{-- Filter periode global (8.5): tautan biasa, halaman dimuat ulang.
             Rata kiri sebagai toolbar halaman; di HP memenuhi lebar layar. --}}
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
            <span id="label-periode" class="inline-flex items-center gap-2 text-sm font-semibold text-obsidian/70">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>
                </svg>
                Periode data
            </span>

            <nav aria-labelledby="label-periode"
                 class="grid grid-cols-3 rounded-field border border-nude bg-ivory p-1 sm:inline-flex">
                @foreach ($periods as $option)
                    @php($active = $option['value'] === $period->value)
                    <a href="{{ route('beranda', ['period' => $option['value']]) }}"
                       @if ($active) aria-current="true" @endif
                       class="rounded-[6px] px-4 py-1.5 text-center text-sm transition-colors {{ $active ? 'bg-obsidian text-offwhite' : 'text-obsidian hover:bg-nude/50' }}">
                        {{ $option['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="mt-6 space-y-12">
            @foreach ($groups as $key => $group)
                <section id="grup-{{ strtolower($key) }}"
                         data-stats-group="{{ $key }}"
                         @if ($group['available']) data-url="{{ route('stats.show', ['group' => $key, 'period' => $period->value]) }}" @endif>

                    {{-- Header section: kotak ikon + judul + deskripsi, info sampel di kanan --}}
                    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-field bg-obsidian text-offwhite"
                                  aria-hidden="true">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    {!! $icons[strtoupper($key)] ?? $iconCadangan !!}
                                </svg>
                            </span>

                            <div class="min-w-0">
                                <h2 class="font-sans text-lg font-semibold not-italic leading-snug">{{ $group['title'] }}</h2>
                                <p class="mt-0.5 text-obsidian/70">{{ $group['description'] }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 text-sm text-obsidian/70">
                            <span data-stats-sample class="rounded-full border border-nude bg-ivory px-3 py-1 text-xs empty:hidden"></span>
                            <button type="button" data-stats-retry class="hidden underline">Coba lagi</button>
                        </div>
                    </div>

                    <div class="mt-4">
                        @if ($group['available'])
                            @includeIf('stats.group-' . strtolower($key))
                        @else
                            <x-card><p class="text-obsidian/70">Segera hadir.</p></x-card>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.app>