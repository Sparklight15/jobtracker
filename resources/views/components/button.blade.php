@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'full' => false,
])

@php
    $ring = 'focus:outline-none focus-visible:ring-1 focus-visible:ring-obsidian focus-visible:ring-offset-2 focus-visible:ring-offset-offwhite';

    $variants = [
        'primary' => "bg-obsidian text-offwhite hover:opacity-90 $ring",
        'outline' => "border border-obsidian text-obsidian hover:bg-ivory $ring",
        'danger' => "bg-error text-offwhite hover:opacity-90 $ring",
        'danger-outline' => "border border-error text-error hover:bg-error/5 $ring",
        'text' => 'text-obsidian/70 underline-offset-4 hover:text-obsidian focus:outline-none focus-visible:underline',
    ];

    $classes = 'inline-flex h-btn items-center justify-center rounded-field px-4 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-40 '
        . ($variants[$variant] ?? $variants['primary'])
        . ($full ? ' w-full' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif