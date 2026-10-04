@props(['value' => null, 'max' => 5])

@if ($value === null)
    -
@else
    <span class="inline-flex items-center gap-2">
        <span class="inline-flex gap-1" aria-hidden="true">
            @for ($i = 1; $i <= $max; $i++)
                <span class="h-2.5 w-2.5 rounded-full {{ $i <= $value ? 'bg-obsidian' : 'bg-nude' }}"></span>
            @endfor
        </span>
        <span>{{ $value }}/{{ $max }}</span>
    </span>
@endif