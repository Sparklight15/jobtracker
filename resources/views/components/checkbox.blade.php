@props(['label' => null])

<label class="inline-flex cursor-pointer items-center gap-2 text-sm">
    <input type="checkbox" {{ $attributes->merge(['class' => 'checkbox']) }}>
    <span>{{ $label ?? $slot }}</span>
</label>