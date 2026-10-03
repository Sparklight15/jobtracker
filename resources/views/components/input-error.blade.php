@props(['messages' => null])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'mt-1 space-y-1 text-xs text-error']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif