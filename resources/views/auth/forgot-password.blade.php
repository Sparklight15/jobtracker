<x-layouts.guest title="Lupa Kata Sandi">
    @if (session('status'))
        {{-- Tampilan setelah tautan berhasil dikirim --}}
        <h1 class="text-center font-display text-h1 italic">Cek email kamu</h1>
        <p class="mt-2 text-center text-sm text-obsidian/70">
            {{ session('status') }} Buka email itu lalu klik tautannya untuk mengatur ulang kata sandi.
        </p>
        <p class="mt-4 text-center text-sm text-obsidian/70">
            Tidak menemukan emailnya? Periksa folder spam, atau tunggu sekitar 1 menit lalu coba kirim ulang.
        </p>

        <div class="mt-6 space-y-2">
            <x-button :href="route('login')" full>Kembali ke halaman masuk</x-button>
            <div class="text-center">
                <x-button :href="route('password.request')" variant="text">Kirim ulang ke email lain</x-button>
            </div>
        </div>
    @else
        {{-- Tampilan awal: form input email --}}
        <h1 class="text-center font-display text-h1 italic">Lupa kata sandi</h1>
        <p class="mt-2 text-center text-sm text-obsidian/70">
            Masukkan email akunmu. Kami akan mengirim tautan untuk mengatur ulang kata sandi.
        </p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
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

            <x-button type="submit" full>Kirim tautan</x-button>

            <div class="text-center">
                <x-button :href="route('login')" variant="text">Kembali ke halaman masuk</x-button>
            </div>
        </form>
    @endif
</x-layouts.guest>