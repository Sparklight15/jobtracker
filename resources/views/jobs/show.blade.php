<x-layouts.app :title="$job->position">
    @php
        $status = $job->current_status;
        $isOffer = $status === \App\Enums\JobStatus::Offer;
        $isRejected = $status === \App\Enums\JobStatus::Rejected;

        $card = 'rounded-card border border-nude bg-ivory p-6 shadow-card';

        // Kelas input (token dari tailwind.config.js). Border merah tipis hanya saat error (5.8).
        $inputBase = 'h-10 w-full min-w-0 rounded-field border bg-offwhite px-3 text-obsidian placeholder:text-obsidian/40 focus:border-obsidian focus:outline-none focus:ring-1 focus:ring-obsidian';
        $field = fn (string $name) => $inputBase.' '.($errors->has($name) ? 'border-error' : 'border-nude');

        // Daftar [nilai => label] dari enum untuk <select>
        $optionsOf = fn (string $enum) => collect($enum::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();

        $rupiah = fn (int $n) => 'Rp '.number_format($n, 0, ',', '.');
        $formatDate = fn ($d) => $d?->locale('id')->translatedFormat('d M Y');

        // Hanya tautan http(s) yang dijadikan link, supaya "javascript:..." tidak bisa lolos
        $url = $job->job_url;
        $safeUrl = ($url && preg_match('#^https?://#i', $url)) ? $url : null;

        $responseDays = $job->first_response_date
            ? (int) $job->applied_date->diffInDays($job->first_response_date, true)
            : null;

        $salaryRange = match (true) {
            $job->salary_min !== null && $job->salary_max !== null => $rupiah($job->salary_min).' – '.$rupiah($job->salary_max),
            $job->salary_min !== null => 'Mulai '.$rupiah($job->salary_min),
            $job->salary_max !== null => 'Hingga '.$rupiah($job->salary_max),
            default => null,
        };
    @endphp

    <div class="space-y-6">
        <a href="{{ $backUrl }}"
           class="inline-flex items-center gap-1 font-semibold text-obsidian/70 underline-offset-4 hover:text-obsidian hover:underline focus:outline-none focus-visible:underline">
            <span aria-hidden="true">←</span> Kembali ke List Loker
        </a>

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="break-words">{{ $job->position }}</h1>
                <p class="mt-1 break-words text-obsidian/70">{{ $job->company_name }}</p>
                <div class="mt-3">
                    <x-status-badge :status="$status" />
                </div>
            </div>

            <div class="flex flex-wrap gap-2" x-data="{ confirmDelete: false }">
                @if (Route::has('jobs.edit'))
                    <x-button variant="outline" :href="route('jobs.edit', $job)">Edit</x-button>
                @endif

                <button type="button" @click="confirmDelete = true"
                        class="inline-flex h-btn items-center justify-center rounded-field border border-error px-4 text-sm font-semibold text-error transition hover:bg-ivory focus:outline-none focus-visible:ring-1 focus-visible:ring-error">
                    Hapus
                </button>

                {{-- Dialog konfirmasi hapus --}}
                <div x-show="confirmDelete" x-cloak
                     x-effect="if (confirmDelete) $nextTick(() => $refs.batal.focus())"
                     @keydown.escape.window="confirmDelete = false"
                     @click.self="confirmDelete = false"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-obsidian/50 px-4">
                    <div role="dialog" aria-modal="true" aria-labelledby="judul-hapus" aria-describedby="isi-hapus"
                         class="w-full max-w-md rounded-card border border-nude bg-offwhite p-6 shadow-card">
                        <h2 id="judul-hapus">Hapus loker ini?</h2>
                        <p id="isi-hapus" class="mt-2 break-words text-obsidian/70">
                            {{ $job->position }} di {{ $job->company_name }} beserta seluruh riwayat statusnya akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.
                        </p>

                        <form method="POST" action="{{ route('jobs.destroy', $job) }}"
                              class="mt-6 flex flex-wrap justify-end gap-2">
                            @csrf
                            @method('DELETE')
                            <button type="button" x-ref="batal" @click="confirmDelete = false"
                                    class="inline-flex h-btn items-center justify-center rounded-field border border-nude px-4 text-sm font-semibold transition hover:bg-ivory focus:outline-none focus-visible:ring-1 focus-visible:ring-obsidian">
                                Batal
                            </button>
                            <button type="submit"
                                    class="inline-flex h-btn items-center justify-center rounded-field bg-error px-4 text-sm font-semibold text-offwhite transition hover:opacity-90 focus:outline-none focus-visible:ring-1 focus-visible:ring-error focus-visible:ring-offset-2 focus-visible:ring-offset-offwhite">
                                Ya, hapus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pesan sukses (flash) --}}
        @if (session('success'))
            <div role="status" class="rounded-field border border-nude bg-ivory px-4 py-3 font-semibold">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Riwayat status --}}
            <section class="{{ $card }}" aria-labelledby="g-riwayat">
                <h2 id="g-riwayat">Riwayat Status</h2>
                @if ($timeline->isEmpty())
                    <p class="mt-4 text-obsidian/70">Belum ada riwayat status.</p>
                @else
                    <div class="mt-6">
                        <x-status-timeline :entries="$timeline" />
                    </div>
                @endif
            </section>

            {{-- Ubah status --}}
            <section class="{{ $card }}" aria-labelledby="g-ubah-status">
                <h2 id="g-ubah-status">Ubah Status</h2>
                <p class="mt-1 text-obsidian/70">
                    Status saat ini: <span class="font-semibold text-obsidian">{{ $status->label() }}</span>
                </p>

                <form method="POST"
                      action="{{ route('jobs.status', $job) }}"
                      class="mt-6 space-y-4"
                      x-data="{ status: @js(old('status', '')) }">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="status" class="font-semibold">Status baru</label>
                        <select id="status" name="status" x-model="status" required class="mt-1 {{ $field('status') }}">
                            <option value="">Pilih status</option>
                            @foreach ($optionsOf(\App\Enums\JobStatus::class) as $value => $label)
                                <option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                        <p x-show="status === 'ghosted'" x-cloak class="mt-1 text-xs text-obsidian/60">
                            Pilih Ghosted kalau perusahaan tidak memberi kabar sama sekali.
                        </p>
                    </div>

                    <div>
                        <label for="changed_at" class="font-semibold">Tanggal perubahan</label>
                        <input id="changed_at" type="date" name="changed_at" required
                               value="{{ old('changed_at', now()->toDateString()) }}"
                               min="{{ $job->applied_date->toDateString() }}"
                               max="{{ now()->toDateString() }}"
                               class="mt-1 {{ $field('changed_at') }}">
                        @error('changed_at')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-obsidian/60">
                            Ubah tanggalnya kalau kamu mencatat kejadian yang sudah lewat.
                        </p>
                    </div>

                    {{-- Hanya relevan untuk Rejected --}}
                    <div x-show="status === 'rejected'" x-cloak>
                        <label for="rejection_reason" class="font-semibold">Alasan penolakan (opsional)</label>
                        <select id="rejection_reason" name="rejection_reason" class="mt-1 {{ $field('rejection_reason') }}">
                            <option value="">Belum diketahui</option>
                            @foreach ($optionsOf(\App\Enums\RejectionReason::class) as $value => $label)
                                <option value="{{ $value }}" @selected(old('rejection_reason') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('rejection_reason')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Hanya relevan untuk Offer --}}
                    <div x-show="status === 'offer'" x-cloak class="space-y-4">
                        <div>
                            <label for="offer_decision" class="font-semibold">Keputusan offer (opsional)</label>
                            <select id="offer_decision" name="offer_decision" class="mt-1 {{ $field('offer_decision') }}">
                                <option value="">Belum ditentukan</option>
                                @foreach ($optionsOf(\App\Enums\OfferDecision::class) as $value => $label)
                                    <option value="{{ $value }}" @selected(old('offer_decision') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('offer_decision')
                                <p class="mt-1 text-xs text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="salary_offered" class="font-semibold">Gaji ditawarkan, dalam Rupiah (opsional)</label>
                            <input id="salary_offered" type="text" name="salary_offered" inputmode="numeric"
                                   value="{{ old('salary_offered') }}" placeholder="Contoh: 8000000"
                                   class="mt-1 {{ $field('salary_offered') }}">
                            @error('salary_offered')
                                <p class="mt-1 text-xs text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="pt-2">
                        <x-button type="submit">Simpan Status</x-button>
                    </div>
                </form>
            </section>

            {{-- Identitas --}}
            <section class="{{ $card }}" aria-labelledby="g-identitas">
                <h2 id="g-identitas">Identitas</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-detail-item label="Perusahaan">{{ $job->company_name }}</x-detail-item>
                    <x-detail-item label="Posisi">{{ $job->position }}</x-detail-item>
                    <x-detail-item label="Sektor industri">{{ $job->industry_sector?->label() }}</x-detail-item>
                    <x-detail-item label="Link lowongan" class="sm:col-span-2">
                        @if ($safeUrl)
                            <a href="{{ $safeUrl }}" target="_blank" rel="noopener noreferrer"
                               class="underline underline-offset-4 hover:no-underline">{{ $safeUrl }}</a>
                        @else
                            {{ $url }}
                        @endif
                    </x-detail-item>
                </dl>
            </section>

            {{-- Waktu --}}
            <section class="{{ $card }}" aria-labelledby="g-waktu">
                <h2 id="g-waktu">Waktu</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-detail-item label="Tanggal apply">{{ $formatDate($job->applied_date) }}</x-detail-item>
                    <x-detail-item label="Respons pertama">{{ $formatDate($job->first_response_date) }}</x-detail-item>
                    <x-detail-item label="Waktu menunggu respons">{{ $responseDays !== null ? $responseDays.' hari' : '' }}</x-detail-item>
                </dl>
            </section>

            {{-- Channel --}}
            <section class="{{ $card }}" aria-labelledby="g-channel">
                <h2 id="g-channel">Channel</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-detail-item label="Channel">{{ $job->channel?->label() }}</x-detail-item>
                    <x-detail-item label="Referral">{{ $job->has_referral ? 'Ya' : 'Tidak' }}</x-detail-item>
                    @if ($job->has_referral)
                        <x-detail-item label="Nama referrer">{{ $job->referrer_name }}</x-detail-item>
                    @endif
                </dl>
            </section>

            {{-- Lokasi --}}
            <section class="{{ $card }}" aria-labelledby="g-lokasi">
                <h2 id="g-lokasi">Lokasi</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-detail-item label="Kota">{{ $job->city }}</x-detail-item>
                    <x-detail-item label="Mode kerja">{{ $job->work_mode?->label() }}</x-detail-item>
                </dl>
            </section>

            {{-- Gaji --}}
            <section class="{{ $card }}" aria-labelledby="g-gaji">
                <h2 id="g-gaji">Gaji</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-detail-item label="Range gaji di lowongan">{{ $salaryRange }}</x-detail-item>
                    @if ($isOffer)
                        <x-detail-item label="Gaji ditawarkan">{{ $job->salary_offered !== null ? $rupiah($job->salary_offered) : '' }}</x-detail-item>
                    @endif
                </dl>
            </section>

            {{-- Penilaian (fit) --}}
            <section class="{{ $card }}" aria-labelledby="g-fit">
                <h2 id="g-fit">Penilaian</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-detail-item label="Kecocokan (fit)"><x-score-dots :value="$job->fit_score" /></x-detail-item>
                    <x-detail-item label="Skill match"><x-score-dots :value="$job->skill_match_score" /></x-detail-item>
                    <x-detail-item label="Kustomisasi CV">{{ $job->cv_customization?->label() }}</x-detail-item>
                </dl>
            </section>

            {{-- Hasil --}}
            <section class="{{ $card }}" aria-labelledby="g-hasil">
                <h2 id="g-hasil">Hasil</h2>
                @if ($isOffer || $isRejected)
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        @if ($isOffer)
                            <x-detail-item label="Keputusan offer">{{ $job->offer_decision?->label() }}</x-detail-item>
                        @endif
                        @if ($isRejected)
                            <div id="alasan-penolakan" class="min-w-0"><x-detail-item label="Alasan penolakan">{{ $job->rejection_reason?->label() }}</x-detail-item></div>
                        @endif
                    </dl>
                @else
                    <p class="mt-4 text-obsidian/70">Belum ada hasil yang perlu dicatat untuk status ini.</p>
                @endif
            </section>

            {{-- Skill yang kurang --}}
            <section class="{{ $card }} lg:col-span-2" aria-labelledby="g-skill">
                <h2 id="g-skill">Skill yang kurang</h2>
                @if ($job->skillGaps->isEmpty())
                    <p class="mt-4 text-obsidian/70">Belum ada skill yang dicatat.</p>
                @else
                    <ul class="mt-4 flex flex-wrap gap-2">
                        @foreach ($job->skillGaps as $gap)
                            <li class="rounded-field border border-nude bg-offwhite px-3 py-1 text-sm">{{ $gap->skill_name }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Catatan --}}
            <section class="{{ $card }} lg:col-span-2" aria-labelledby="g-catatan">
                <h2 id="g-catatan">Catatan</h2>
                @if (filled($job->notes))
                    <p class="mt-4 whitespace-pre-line break-words">{{ $job->notes }}</p>
                @else
                    <p class="mt-4 text-obsidian/70">Belum ada catatan.</p>
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>