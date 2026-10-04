@props(['entries'])

<ol class="relative ml-2 border-l border-nude">
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

        <li class="relative pb-8 pl-8 last:pb-0">
            <span aria-hidden="true"
                  class="absolute -left-[7.5px] top-1 h-3.5 w-3.5 rounded-full border border-obsidian {{ $entry['isCurrent'] ? 'bg-obsidian' : 'bg-offwhite' }}"></span>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <x-status-badge :status="$entry['status']" />
                <time datetime="{{ $entry['date']->toDateString() }}" class="text-obsidian/70">
                    {{ $entry['date']->locale('id')->translatedFormat('d M Y') }}
                </time>
                @if ($entry['isCurrent'])
                    <span class="text-xs font-semibold">Status saat ini</span>
                @endif
            </div>

            @if ($duration)
                <p class="mt-1 text-xs text-obsidian/60">{{ $duration }}</p>
            @endif
        </li>
    @endforeach
</ol>