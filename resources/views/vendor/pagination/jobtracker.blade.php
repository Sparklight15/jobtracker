@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman">
        <ul class="flex items-center gap-1">
            {{-- Sebelumnya --}}
            <li>
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true"
                          class="inline-flex h-9 items-center rounded-field border border-nude px-3 font-semibold opacity-40">
                        Sebelumnya
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                       class="inline-flex h-9 items-center rounded-field border border-nude px-3 font-semibold hover:bg-ivory focus:outline-none focus-visible:ring-1 focus-visible:ring-obsidian">
                        Sebelumnya
                    </a>
                @endif
            </li>

            {{-- Nomor halaman (di mobile hanya halaman aktif yang tampil) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="hidden sm:block">
                        <span class="px-2 text-obsidian/60">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li>
                                <span aria-current="page"
                                      class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-field border border-obsidian bg-obsidian px-2 font-semibold text-offwhite">
                                    {{ $page }}
                                </span>
                            </li>
                        @else
                            <li class="hidden sm:block">
                                <a href="{{ $url }}" aria-label="Ke halaman {{ $page }}"
                                   class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-field border border-nude px-2 font-semibold hover:bg-ivory focus:outline-none focus-visible:ring-1 focus-visible:ring-obsidian">
                                    {{ $page }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Berikutnya --}}
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                       class="inline-flex h-9 items-center rounded-field border border-nude px-3 font-semibold hover:bg-ivory focus:outline-none focus-visible:ring-1 focus-visible:ring-obsidian">
                        Berikutnya
                    </a>
                @else
                    <span aria-disabled="true"
                          class="inline-flex h-9 items-center rounded-field border border-nude px-3 font-semibold opacity-40">
                        Berikutnya
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif