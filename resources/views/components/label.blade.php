@props(['value' => null, 'required' => false])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-obsidian']) }}>
    {{ $value ?? $slot }}@if ($required)<span class="text-obsidian/60" aria-hidden="true"> *</span>@endif
</label>