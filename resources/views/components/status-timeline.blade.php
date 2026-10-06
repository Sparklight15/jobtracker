@props(['entries'])

@once
    <style>
        /* Timeline status: CSS sendiri (tidak bergantung build Tailwind).
           Token mengikuti Design System 5.2/5.3; jarak kelipatan 4px. */
        .tl { list-style: none; margin: 0 0 0 .5rem; padding: 0; border-left: 1px solid color-mix(in srgb, currentColor 25%, transparent); }
        .tl-item { position: relative; padding: 0 0 1.5rem 1.75rem; }
        .tl-item:last-child { padding-bottom: 0; }

        /* Titik di garis: kosong = tahap lampau, terisi = tahap saat ini */
        .tl-dot { position: absolute; top: .25rem; left: calc(-.5rem - .5px);
                  width: 1rem; height: 1rem; border-radius: 9999px; box-sizing: border-box;
                  border: 1px solid var(--obsidian, #101010); background: var(--offwhite, #FDFCF8); }
        .tl-item.is-current .tl-dot { background: var(--obsidian, #101010); }

        /* Baris utama: badge, tanggal, penanda "saat ini" dengan jarak jelas */
        .tl-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }
        .tl-date { font-size: .875rem; font-weight: 600; }
        .tl-now { font-size: .75rem; font-weight: 600; padding: .25rem .5rem; border-radius: 9999px;
                  background: color-mix(in srgb, currentColor 10%, transparent); }
        .tl-dur { margin: .5rem 0 0; font-size: .75rem; color: rgb(16 16 16 / .6); }
    </style>
@endonce

<ol class="tl">
    @foreach ($entries as $entry)
        @php
            $days = $entry['days'];

            if ($days === null) {
                $duration = null;
            } elseif ($entry['isCurrent']) {
                // Tahap terakhir yang masih berjalan
                $duration = $days === 0 ? 'Baru dimulai hari ini' : "Sudah {$days} hari di tahap ini";
            } else {
                $duration = 'Lama tahap: '.($days === 0 ? 'kurang dari 1 hari' : "{$days} hari");
            }
        @endphp

        <li class="tl-item{{ $entry['isCurrent'] ? ' is-current' : '' }}">
            <span aria-hidden="true" class="tl-dot"></span>

            <div class="tl-row">
                <x-status-badge :status="$entry['status']" />
                <time datetime="{{ $entry['date']->toDateString() }}" class="tl-date">
                    {{ $entry['date']->locale('id')->translatedFormat('d M Y') }}
                </time>
                @if ($entry['isCurrent'])
                    <span class="tl-now">Status saat ini</span>
                @endif
            </div>

            @if ($duration)
                <p class="tl-dur">{{ $duration }}</p>
            @endif
        </li>
    @endforeach
</ol>