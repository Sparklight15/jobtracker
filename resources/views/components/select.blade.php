@props([
    'options' => [],      // [nilai => label], mis. dari Enum::options()
    'selected' => null,
    'placeholder' => null, // teks opsi kosong (value "")
    'disabled' => false,
    'invalid' => false,
])

@php
    $name = $attributes->get('name');
    $hasError = $invalid || ($name && isset($errors) && $errors->has($name));

    $state = $hasError
        ? 'border-error focus:border-error focus:ring-1 focus:ring-error'
        : 'border-nude focus:border-obsidian focus:ring-1 focus:ring-obsidian';
@endphp

<select
    @disabled($disabled)
    @if ($hasError) aria-invalid="true" @endif
    {{ $attributes->merge([
        'class' => "block h-btn w-full rounded-field border bg-offwhite px-3 text-base text-obsidian focus:outline-none disabled:cursor-not-allowed disabled:bg-ivory disabled:text-obsidian/40 sm:text-sm $state",
    ]) }}
>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}</option>
    @endforeach
</select>