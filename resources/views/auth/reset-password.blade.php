<x-layouts.guest title="Atur Ulang Kata Sandi">
    <h1 class="text-center font-display text-h1 italic">Atur ulang kata sandi</h1>
    <p class="mt-2 text-center text-sm text-obsidian/70">
        Buat kata sandi baru untuk akunmu. Minimal 8 karakter.
    </p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

{{-- Email: terkunci --}}
<div>
    <x-label for="email" value="Email" />
    <div class="mt-1" style="cursor: not-allowed;">
        <x-input
            id="email"
            style="background-color: #F3F0E9; color: rgba(16, 16, 16, 0.5); pointer-events: none; user-select: none;"
            type="email"
            name="email"
            :value="old('email', $request->email)"
            readonly
            tabindex="-1"
            aria-readonly="true"
            autocomplete="username"
        />
    </div>
    <x-input-error :messages="$errors->get('email')" />
</div>

        {{-- Kata sandi baru --}}
        <div>
            <x-label for="password" value="Kata sandi baru" />
            <div x-data="{ show: false }" class="relative mt-1">
                <x-input
                    id="password"
                    type="password"
                    x-bind:type="show ? 'text' : 'password'"
                    class="pr-10"
                    name="password"
                    required
                    autofocus
                    autocomplete="new-password"
                />
                <button
                    type="button"
                    @click="show = !show"
                    x-bind:aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-obsidian/60 hover:text-obsidian focus:outline-none focus-visible:text-obsidian"
                >
                    <svg x-show="!show" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 0 1 0-.64C3.42 8.2 7.36 5 12 5s8.58 3.2 9.96 6.68a1 1 0 0 1 0 .64C20.58 15.8 16.64 19 12 19s-8.58-3.2-9.96-6.68Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg x-show="show" style="display: none" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.5 10.5 0 0 0 2.04 12c1.38 3.48 5.32 6.68 9.96 6.68 1.8 0 3.5-.48 4.98-1.3M6.23 6.23A10.45 10.45 0 0 1 12 5c4.64 0 8.58 3.2 9.96 6.68a10.5 10.5 0 0 1-4.3 5.1M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" />
        </div>

        {{-- Konfirmasi kata sandi baru --}}
        <div>
            <x-label for="password_confirmation" value="Konfirmasi kata sandi baru" />
            <div x-data="{ show: false }" class="relative mt-1">
                <x-input
                    id="password_confirmation"
                    type="password"
                    x-bind:type="show ? 'text' : 'password'"
                    class="pr-10"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <button
                    type="button"
                    @click="show = !show"
                    x-bind:aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-obsidian/60 hover:text-obsidian focus:outline-none focus-visible:text-obsidian"
                >
                    <svg x-show="!show" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 0 1 0-.64C3.42 8.2 7.36 5 12 5s8.58 3.2 9.96 6.68a1 1 0 0 1 0 .64C20.58 15.8 16.64 19 12 19s-8.58-3.2-9.96-6.68Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg x-show="show" style="display: none" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.5 10.5 0 0 0 2.04 12c1.38 3.48 5.32 6.68 9.96 6.68 1.8 0 3.5-.48 4.98-1.3M6.23 6.23A10.45 10.45 0 0 1 12 5c4.64 0 8.58 3.2 9.96 6.68a10.5 10.5 0 0 1-4.3 5.1M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <x-button type="submit" full>Simpan kata sandi</x-button>

        <div class="text-center">
            <x-button :href="route('login')" variant="text">Kembali ke halaman masuk</x-button>
        </div>
    </form>
</x-layouts.guest>