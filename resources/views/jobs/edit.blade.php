<x-layouts.app title="Edit Loker">
    <div class="space-y-6">
        <a href="{{ route('jobs.show', $job) }}"
           class="inline-flex items-center gap-1 font-semibold text-obsidian/70 underline-offset-4 hover:text-obsidian hover:underline focus:outline-none focus-visible:underline">
            <span aria-hidden="true">←</span> Kembali ke Detail Loker
        </a>

        <div>
            <h1>Edit Loker</h1>
            <p class="mt-1 break-words text-obsidian/70">{{ $job->position }} di {{ $job->company_name }}</p>
            <p class="mt-1 text-obsidian/70">Kolom bertanda <span class="text-error" aria-hidden="true">*</span><span class="sr-only">bintang</span> wajib diisi.</p>
        </div>

        @include('jobs._form', [
            'job' => $job,
            'action' => route('jobs.update', $job),
            'method' => 'PUT',
            'submitLabel' => 'Simpan Perubahan',
            'cancelUrl' => route('jobs.show', $job),
        ])
    </div>
</x-layouts.app>