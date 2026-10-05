<x-layouts.app title="Tambah Loker">
    <div class="space-y-6">
        <a href="{{ route('jobs.index') }}"
           class="inline-flex items-center gap-1 font-semibold text-obsidian/70 underline-offset-4 hover:text-obsidian hover:underline focus:outline-none focus-visible:underline">
            <span aria-hidden="true">←</span> Kembali ke List Loker
        </a>

        <div>
            <h1>Tambah Loker</h1>
            <p class="mt-1 text-obsidian/70">Kolom bertanda <span class="text-error" aria-hidden="true">*</span><span class="sr-only">bintang</span> wajib diisi.</p>
        </div>

        @include('jobs._form', [
            'job' => null,
            'action' => route('jobs.store'),
            'method' => 'POST',
            'submitLabel' => 'Simpan Loker',
            'cancelUrl' => route('jobs.index'),
        ])
    </div>
</x-layouts.app>