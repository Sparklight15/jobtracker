<x-layouts.app title="Komponen">
    <h1>Komponen</h1>

    <h2 class="mt-8">Tombol</h2>
    <div class="mt-4 flex flex-wrap gap-3">
        <x-button>Primary</x-button>
        <x-button variant="outline">Outline</x-button>
        <x-button variant="text">Text link</x-button>
        <x-button disabled>Disabled</x-button>
        <x-button variant="outline" disabled>Disabled</x-button>
    </div>
    <div class="mt-3 max-w-auth">
        <x-button full>Full width</x-button>
    </div>

    <h2 class="mt-8">Form</h2>
    <x-card class="mt-4 max-w-auth space-y-4">
        <div>
            <x-label for="email" value="Email" required />
            <x-input id="email" name="email" type="email" placeholder="nama@email.com" class="mt-1" />
        </div>
        <div>
            <x-label for="email2" value="Email (error)" />
            <x-input id="email2" type="email" value="salah" invalid class="mt-1" />
            <x-input-error :messages="['Format email tidak valid.']" />
        </div>
        <div>
            <x-label for="email3" value="Email (disabled)" />
            <x-input id="email3" type="email" value="terkunci@email.com" disabled class="mt-1" />
        </div>
        <div class="space-y-2">
            <div><x-checkbox label="Ingat saya" /></div>
            <div><x-checkbox label="Tercentang" checked /></div>
            <div><x-checkbox label="Disabled" disabled /></div>
        </div>
    </x-card>

    <h2 class="mt-8">Badge status</h2>
    <div class="mt-4 flex flex-wrap gap-2">
        <x-badge status="applied" />
        <x-badge status="screening" />
        <x-badge status="interview" />
        <x-badge status="offer" />
        <x-badge status="rejected" />
        <x-badge status="ghosted" />
    </div>
</x-layouts.app>