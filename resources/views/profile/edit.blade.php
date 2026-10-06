<x-layouts.app title="Profil">
    <div class="mx-auto max-w-2xl space-y-8">
        <div>
            <h1>Profil</h1>
            <p class="mt-1 text-sm text-obsidian/70">
                Kelola data akun, target lamaran, dan keamanan akunmu.
            </p>
        </div>

        {{-- Info akun dan target lamaran: satu form, karena ProfileUpdateRequest memvalidasi semuanya sekaligus --}}
        <section aria-labelledby="judul-akun">
            <x-card>
                <form method="POST" action="{{ route('profile.update') }}" class="space-y-8">
                    @csrf
                    @method('PATCH')

                    @if (session('status') === 'profile-updated')
                        <p role="status" class="rounded-field border border-nude bg-offwhite px-3 py-2 text-sm">
                            Perubahan tersimpan.
                        </p>
                    @endif

                    <div class="space-y-4">
                        <div>
                            <h2 id="judul-akun">Info akun</h2>
                            <p class="mt-1 text-sm text-obsidian/70">Nama dan email yang terhubung dengan akunmu.</p>
                        </div>

                        <div>
                            <x-label for="name" value="Nama" />
                            <x-input
                                id="name"
                                class="mt-1"
                                name="name"
                                :value="old('name', $user->name)"
                                placeholder="Nama lengkap"
                                autocomplete="name"
                            />
                            <x-input-error :messages="$errors->get('name')" />
                        </div>

                        {{-- Email hanya ditampilkan: disabled, tidak ikut terkirim, tidak bisa diubah --}}
                        <div>
                            <x-label for="email" value="Email" />
                            <x-input
                                id="email"
                                class="mt-1"
                                type="email"
                                name="email"
                                :value="$user->email"
                                disabled
                                aria-describedby="bantuan-email"
                            />
                            <p id="bantuan-email" class="mt-1 text-xs text-obsidian/70">
                                Email dipakai untuk masuk dan tidak bisa diubah.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4 border-t border-nude pt-8">
                        <div>
                            <h2>Pencarian kerja</h2>
                            <p class="mt-1 text-sm text-obsidian/70">
                                Dipakai untuk memantau progres lamaranmu terhadap target.
                            </p>
                        </div>

                        <div>
                            <x-label for="job_search_started_at" value="Mulai mencari kerja" />
                            <x-input
                                id="job_search_started_at"
                                class="mt-1"
                                type="date"
                                name="job_search_started_at"
                                :value="old('job_search_started_at', $user->job_search_started_at?->format('Y-m-d'))"
                                :max="now()->toDateString()"
                            />
                            <x-input-error :messages="$errors->get('job_search_started_at')" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-label for="apply_target" value="Target lamaran" />
                                <x-input
                                    id="apply_target"
                                    class="mt-1"
                                    type="number"
                                    name="apply_target"
                                    min="1"
                                    max="1000"
                                    inputmode="numeric"
                                    :value="old('apply_target', $user->apply_target)"
                                    placeholder="Contoh: 10"
                                />
                                <x-input-error :messages="$errors->get('apply_target')" />
                            </div>

                            <div>
                                <x-label for="apply_target_period" value="Periode" />
                                <x-select
                                    id="apply_target_period"
                                    class="mt-1"
                                    name="apply_target_period"
                                    :options="$periodOptions"
                                    :selected="old('apply_target_period', $user->apply_target_period?->value)"
                                    placeholder="Pilih periode"
                                />
                                <x-input-error :messages="$errors->get('apply_target_period')" />
                            </div>
                        </div>

                        <p class="text-xs text-obsidian/70">
                            Kosongkan target dan periode jika kamu belum ingin menetapkan target.
                        </p>
                    </div>

                    <x-button type="submit">Simpan perubahan</x-button>
                </form>
            </x-card>
        </section>

        {{-- Reset kata sandi lewat email --}}
        <section aria-labelledby="judul-sandi">
            <x-card>
                <form method="POST" action="{{ route('profile.password-reset-link') }}" class="space-y-4">
                    @csrf

                    <div>
                        <h2 id="judul-sandi">Reset kata sandi</h2>
                        <p class="mt-1 text-sm text-obsidian/70">
                            Kami akan mengirim link reset kata sandi ke {{ $user->email }}.
                            Buka link itu untuk membuat kata sandi baru.
                        </p>
                    </div>

                    @if (session('status') === 'password-reset-link-sent')
                        <p role="status" class="rounded-field border border-nude bg-offwhite px-3 py-2 text-sm">
                            Link reset sudah dikirim ke {{ $user->email }}. Cek kotak masuk atau folder spam.
                        </p>
                    @endif

                    <x-input-error :messages="$errors->passwordReset->get('password_reset')" />

                    <x-button type="submit">Kirim link reset password</x-button>
                </form>
            </x-card>
        </section>

        {{-- Hapus akun --}}
        <section aria-labelledby="judul-hapus">
            <x-card>
                <div x-data="{ open: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
                    <h2 id="judul-hapus">Hapus akun</h2>
                    <p class="mt-1 text-sm text-obsidian/70">
                        Akun dan semua data lamaranmu (loker, riwayat status, dan skill gap) dihapus permanen
                        dan tidak bisa dikembalikan.
                    </p>

                    <div class="mt-4" x-show="!open">
                        <x-button
                            variant="danger-outline"
                            @click="open = true"
                            aria-controls="form-hapus-akun"
                            x-bind:aria-expanded="open.toString()"
                        >
                            Hapus akun
                        </x-button>
                    </div>

                    <form
                        id="form-hapus-akun"
                        method="POST"
                        action="{{ route('profile.destroy') }}"
                        class="mt-4 space-y-4"
                        x-show="open"
                        x-cloak
                    >
                        @csrf
                        @method('DELETE')

                        <div>
                            <x-label for="hapus_password" value="Masukkan kata sandi untuk konfirmasi" />
                            <x-input
                                id="hapus_password"
                                class="mt-1"
                                type="password"
                                name="password"
                                :invalid="$errors->userDeletion->has('password')"
                                autocomplete="current-password"
                            />
                            <x-input-error :messages="$errors->userDeletion->get('password')" />
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <x-button type="submit" variant="danger">Hapus akun saya</x-button>
                            <x-button variant="outline" @click="open = false">Batal</x-button>
                        </div>
                    </form>
                </div>
            </x-card>
        </section>
    </div>
</x-layouts.app>