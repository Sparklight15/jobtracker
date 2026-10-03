@props(['status'])

@php
    $key = strtolower($status);

    $map = [
        'applied'   => 'border-dashed border-obsidian/50 text-obsidian/70',
        'screening' => 'border-obsidian text-obsidian',
        'interview' => 'border-obsidian text-obsidian',
        'offer'     => 'border-obsidian bg-obsidian text-offwhite',
        'rejected'  => 'border-nude bg-nude text-obsidian/60',
        'ghosted'   => 'border-nude bg-nude text-obsidian/60',
    ];

    $style = $map[$key] ?? $map['applied'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold $style"]) }}>
    {{ $slot->isEmpty() ? ucfirst($key) : $slot }}
</span>