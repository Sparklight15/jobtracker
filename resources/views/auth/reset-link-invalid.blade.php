<x-layouts.guest title="Tautan Tidak Berlaku">
    @if ($expired)
        <h1 class="text-center font-display text-h1 italic">Tautan sudah kedaluwarsa</h1>
        <p class="mt-2 text-center text-sm text-obsidian/70">
            Tautan atur ulang kata sandi hanya berlaku {{ $menit }} menit. Minta tautan baru untuk melanjutkan.
        </p>
    @else
        <h1 class="text-center font-display text-h1 italic">Tautan tidak berlaku</h1>
        <p class="mt-2 text-center text-sm text-obsidian/70">
            Tautan ini tidak valid atau sudah pernah dipakai. Setiap tautan hanya bisa dipakai satu kali.
        </p>
    @endif

    <div class="mt-6 space-y-2">
        <x-button :href="route('password.request')" full>Minta tautan baru</x-button>
        <div class="text-center">
            <x-button :href="route('login')" variant="text">Kembali ke halaman masuk</x-button>
        </div>
    </div>
</x-layouts.guest>