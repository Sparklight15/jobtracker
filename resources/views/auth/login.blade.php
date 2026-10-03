<x-layouts.guest title="Masuk">
    <h1 class="text-center font-display text-h1 italic">{{ config('app.name') }}</h1>
    <p class="mt-1 text-center text-sm text-obsidian/70">Masuk untuk mencatat dan memantau lamaran kerjamu.</p>

    @if (session('status'))
        <p class="mt-4 rounded-field border border-nude bg-offwhite px-3 py-2 text-sm">
            {{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-label for="email" value="Email" />
            <x-input
                id="email"
                class="mt-1"
                type="email"
                name="email"
                :value="old('email')"
                placeholder="nama@email.com"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-label for="password" value="Kata sandi" />
            <x-input
                id="password"
                class="mt-1"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <x-checkbox name="remember" label="Ingat saya" />

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-sm text-obsidian/70 underline-offset-4 hover:text-obsidian hover:underline focus:outline-none focus-visible:underline">
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        <x-button type="submit" full>Masuk</x-button>
    </form>
</x-layouts.guest>