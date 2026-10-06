<x-layouts.app title="Beranda">
    <div data-stats-root>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1>Beranda</h1>
                <p class="mt-1 text-obsidian/70">Ringkasan lamaran kerjamu.</p>
            </div>

            {{-- Filter periode global (8.5): tautan biasa, halaman dimuat ulang --}}
            <nav aria-label="Periode statistik" class="inline-flex rounded-field border border-nude bg-offwhite p-1">
                @foreach ($periods as $option)
                    @php($active = $option['value'] === $period->value)
                    <a href="{{ route('beranda', ['period' => $option['value']]) }}"
                       @if ($active) aria-current="true" @endif
                       class="rounded-[6px] px-3 py-1.5 text-sm transition-colors {{ $active ? 'bg-obsidian text-offwhite' : 'text-obsidian hover:bg-ivory' }}">
                        {{ $option['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Menu anchor --}}
        <nav aria-label="Bagian statistik"
             class="sticky top-0 z-10 mt-6 flex gap-2 overflow-x-auto border-b border-nude bg-offwhite/90 py-3 backdrop-blur">
            @foreach ($groups as $key => $group)
                <a href="#grup-{{ strtolower($key) }}"
                   class="shrink-0 rounded-full border border-nude px-3 py-1 text-sm hover:bg-ivory {{ $group['available'] ? '' : 'text-obsidian/40' }}">
                    {{ $key }} · {{ $group['title'] }}
                </a>
            @endforeach
        </nav>

        <div class="mt-8 space-y-12">
            @foreach ($groups as $key => $group)
                <section id="grup-{{ strtolower($key) }}" class="scroll-mt-16"
                         data-stats-group="{{ $key }}"
                         @if ($group['available']) data-url="{{ route('stats.show', ['group' => $key, 'period' => $period->value]) }}" @endif>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <p class="text-sm text-obsidian/70">Grup {{ $key }}</p>
                            <h2>{{ $group['title'] }}</h2>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-obsidian/70">
                            <span data-stats-sample></span>
                            <button type="button" data-stats-retry class="hidden underline">Coba lagi</button>
                        </div>
                    </div>
                    <p class="mt-1 text-obsidian/70">{{ $group['description'] }}</p>

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