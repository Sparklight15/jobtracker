@props(['label'])

@php
    $isEmpty = trim((string) $slot) === '';
@endphp

<div {{ $attributes->class(['min-w-0']) }}>
    <dt class="text-xs text-obsidian/60">{{ $label }}</dt>
    <dd class="mt-1 break-words">
        @if ($isEmpty)
            -
        @else
            {{ $slot }}
        @endif
    </dd>
</div>