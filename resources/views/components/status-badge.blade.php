@props(['status'])

@php
    // Pemetaan 5.7: Awal = putus-putus, Proses = border solid, Hasil = solid gelap, Selesai/Negatif = Nude
    $classes = match ($status) {
        \App\Enums\JobStatus::Applied => 'border-dashed border-obsidian/70 text-obsidian/70',
        \App\Enums\JobStatus::Screening, \App\Enums\JobStatus::Interview => 'border-obsidian text-obsidian',
        \App\Enums\JobStatus::Offer => 'border-obsidian bg-obsidian text-offwhite',
        \App\Enums\JobStatus::Rejected, \App\Enums\JobStatus::Ghosted => 'border-nude bg-nude text-obsidian/60',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-semibold', $classes]) }}>
    {{ $status->label() }}
</span>