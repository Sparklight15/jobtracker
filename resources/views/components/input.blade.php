@props(['disabled' => false, 'invalid' => false])

@php
    $name = $attributes->get('name');
    $hasError = $invalid || ($name && isset($errors) && $errors->has($name));

    $state = $hasError
        ? 'border-error focus:border-error focus:ring-1 focus:ring-error'
        : 'border-nude focus:border-obsidian focus:ring-1 focus:ring-obsidian';
@endphp

<input
    @disabled($disabled)
    @if ($hasError) aria-invalid="true" @endif
    {{ $attributes->merge([
        'type' => 'text',
        'class' => "block h-btn w-full rounded-field border bg-offwhite px-3 text-base text-obsidian placeholder:text-obsidian/40 focus:outline-none disabled:cursor-not-allowed disabled:bg-ivory disabled:text-obsidian/40 sm:text-sm $state",
    ]) }}
>