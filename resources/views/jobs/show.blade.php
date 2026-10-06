<x-layouts.app :title="$job->position">
    <style>
        /* ===== Halaman detail: lembar formulir =====
           Token mengikuti Design System 5.2 (palet), 5.3 (tipografi), 5.5 (bentuk), 5.8 (state).
           Jarak memakai kelipatan 4px: 4, 8, 12, 16, 24, 32. */
        .jd { --obsidian: #101010; --offwhite: #FDFCF8; --ivory: #F3F0E9; --nude: #E3DBCC;
              --ink-70: rgb(16 16 16 / .7); --ink-60: rgb(16 16 16 / .6);
              --jd-line: var(--nude);   /* garis pemisah baris: Nude, sama seperti tabel List Loker */
              --jd-tint: color-mix(in srgb, currentColor 6%, transparent);    /* hover ikon */
              --jd-title-w: 10rem;      /* lebar kolom judul section */
              --jd-label-w: 10rem; }    /* lebar kolom label */

        /* Judul halaman + badge status */
        .jd-title { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }

        .jd-sheet { overflow: hidden; }

        /* Gaya mengikuti tabel List Loker: card Ivory, tanpa latar sel, hanya garis horizontal tipis.
           Satu section = kolom judul (kiri) + tabel isi (kanan) */
        .jd-sec { display: grid; grid-template-columns: var(--jd-title-w) minmax(0, 1fr); }
        .jd-sec + .jd-sec { border-top: 1px solid var(--jd-line); }
        .jd-sec-t { padding: .75rem 1rem; }
        .jd-sec-t h2 { margin: 0; line-height: 1.2; }          /* ukuran H2 24px dari gaya global */
        .jd-sec-b { min-width: 0; overflow: hidden; }          /* memotong garis tepi yang menjorok 1px */

        /* Tabel isi: label | isi | label | isi */
        .jd-rows { display: grid; grid-template-columns: var(--jd-label-w) minmax(0, 1fr) var(--jd-label-w) minmax(0, 1fr);
                   margin: -1px 0 0 0; }
        .jd-l, .jd-v, .jd-row { border-top: 1px solid var(--jd-line); min-width: 0; }

        /* Label = caption 12px Obsidian 60%. Isi = 14px Nunito semibold. Tanpa latar sel. */
        .jd-l { display: flex; align-items: center; padding: .75rem 1rem; margin: 0;
                font-size: .75rem; line-height: 1.33; font-weight: 400; color: var(--ink-60); }
        .jd-v { display: flex; align-items: center; flex-wrap: wrap; gap: .25rem .5rem; padding: .75rem 1rem; margin: 0;
                font-size: .875rem; line-height: 1.4; font-weight: 600; overflow-wrap: anywhere; }
        .jd-v.is-empty { font-weight: 400; color: var(--ink-60); }
        .jd-w3 { grid-column: span 3; }
        .jd-row { grid-column: 1 / -1; padding: .75rem 1rem; }
        .jd-in { display: block; padding: .25rem .5rem; font-weight: 400; }
        .jd-link { font-weight: 600; text-decoration: underline; text-underline-offset: 4px; }
        .jd-link:hover { text-decoration: none; }
        .jd-empty { font-size: .875rem; font-weight: 400; color: var(--ink-60); }
        .jd-meta { font-size: .875rem; color: var(--ink-70); }
        .jd-hintcell { font-size: .75rem; color: var(--ink-60); padding-top: .5rem; padding-bottom: .5rem; }
        .jd-contents { display: contents; }
        .jd-act { display: flex; justify-content: flex-end; }
        .jd-chip { display: inline-block; padding: .25rem .75rem; font-size: .875rem; font-weight: 600; line-height: 1.4;
                   background: var(--offwhite); border: 1px solid var(--nude); border-radius: .5rem; }
        .jd-note { font-size: .875rem; line-height: 1.6; }

        /* Penilaian: ikon "!" di pojok kanan atas tiap nilai + popover penjelasan masing-masing */
        .jd-has-tip { position: relative; }
        .jd-tipcell { position: relative; padding-right: 2.5rem; }
        .jd-tipbtn { position: absolute; top: .5rem; right: .5rem; z-index: 2; cursor: pointer; padding: 0;
                     display: inline-flex; align-items: center; justify-content: center;
                     width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: transparent; color: var(--obsidian);
                     border: 1px solid var(--obsidian); font-size: .75rem; font-weight: 700; line-height: 1; }
        .jd-tipbtn:hover, .jd-tipbtn[aria-expanded="true"] { background: var(--jd-tint); }
        .jd-tipbtn:focus-visible { outline: none; box-shadow: 0 0 0 1px var(--obsidian); }   /* ring tipis Obsidian */
        .jd-tip { position: absolute; top: 3rem; z-index: 30;
                  width: min(22rem, calc(100% - 1rem)); padding: 1rem;
                  background-color: var(--offwhite); border: 1px solid var(--nude); border-radius: 1rem;
                  box-shadow: 0 1px 2px rgb(0 0 0 / .05); font-size: .875rem; line-height: 1.5; }
        .jd-tip-fit { left: calc(var(--jd-title-w) + var(--jd-label-w)); }   /* di bawah sel Kecocokan */
        .jd-tip-skill { right: .5rem; }                                      /* di bawah sel Skill match */
        .jd-tip p { margin: 0; }
        .jd-tip-t { font-weight: 600; }
        .jd-tip-m { font-size: .75rem; color: var(--ink-60); margin-top: .25rem !important; }
        .jd-tip table { width: 100%; border-collapse: collapse; margin-top: .75rem; }
        .jd-tip th, .jd-tip td { padding: .5rem; text-align: left; vertical-align: top; border-top: 1px solid var(--jd-line); }
        .jd-tip thead th { border-top: 0; padding-top: 0; font-size: .75rem; font-weight: 600; color: var(--ink-60); }
        .jd-tip tbody td:first-child { width: 2.5rem; font-weight: 600; white-space: nowrap; }

        /* Layar sempit: judul section jadi pita di atas, tabel jadi label | isi */
        @media (max-width: 760px) {
            .jd-sec { grid-template-columns: minmax(0, 1fr); }
            .jd-sec-t { padding: .5rem 1rem; }
            .jd-rows { grid-template-columns: 8rem minmax(0, 1fr); }
            .jd-w3 { grid-column: span 1; }
            .jd-tip-fit, .jd-tip-skill { left: .5rem; right: .5rem; width: auto; }
        }
    </style>
    @php
        $status = $job->current_status;
        $isOffer = $status === \App\Enums\JobStatus::Offer;
        $isRejected = $status === \App\Enums\JobStatus::Rejected;

        // Lembar formulir. Layout & tipografi ada di <style> di atas (tidak bergantung build Tailwind).
        $sheet = 'jd-sheet rounded-card border border-nude bg-ivory shadow-card';

        // Sepasang sel formulir: label + isi. $wide = isi melebar menutup 3 kolom (sisa baris).
        $cell = function (string $label, $value, bool $wide = false) {
            $empty = ! filled($value);

            return new \Illuminate\Support\HtmlString(
                '<dt class="jd-l">'.e($label).'</dt>'
                .'<dd class="jd-v'.($wide ? ' jd-w3' : '').($empty ? ' is-empty' : '').'">'.e($empty ? '-' : $value).'</dd>'
            );
        };

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

    <div class="jd space-y-4">
        <a href="{{ $backUrl }}"
           class="inline-flex items-center gap-1 font-semibold text-obsidian/70 underline-offset-4 hover:text-obsidian hover:underline focus:outline-none focus-visible:underline">
            <span aria-hidden="true">←</span> Kembali ke List Loker
        </a>

        {{-- Header: judul + badge sejajar, perusahaan di bawahnya --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="jd-title">
                    <h1 class="break-words">{{ $job->position }}</h1>
                    <x-status-badge :status="$status" />
                </div>
                <p class="mt-1 break-words text-obsidian/70">{{ $job->company_name }}</p>
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

        {{-- Lembar formulir: tiap section = judul di kiri + tabel label/isi di kanan --}}
        <div class="{{ $sheet }}">

            {{-- Identitas --}}
            <section class="jd-sec" aria-labelledby="g-identitas">
                <div class="jd-sec-t"><h2 id="g-identitas">Identitas</h2></div>
                <div class="jd-sec-b"><dl class="jd-rows">
                    {{ $cell('Perusahaan', $job->company_name) }}
                    {{ $cell('Posisi', $job->position) }}
                    {{ $cell('Sektor industri', $job->industry_sector?->label()) }}
                    <dt class="jd-l">Link lowongan</dt>
                    <dd class="jd-v{{ filled($url) ? '' : ' is-empty' }}">
                        @if ($safeUrl)
                            <a href="{{ $safeUrl }}" target="_blank" rel="noopener noreferrer" class="jd-link">{{ $safeUrl }}</a>
                        @else
                            {{ filled($url) ? $url : '-' }}
                        @endif
                    </dd>
                </dl></div>
            </section>

            {{-- Waktu --}}
            <section class="jd-sec" aria-labelledby="g-waktu">
                <div class="jd-sec-t"><h2 id="g-waktu">Waktu</h2></div>
                <div class="jd-sec-b"><dl class="jd-rows">
                    {{ $cell('Tanggal apply', $formatDate($job->applied_date)) }}
                    {{ $cell('Respons pertama', $formatDate($job->first_response_date)) }}
                    {{ $cell('Waktu menunggu respons', $responseDays !== null ? $responseDays.' hari' : '', true) }}
                </dl></div>
            </section>

            {{-- Channel --}}
            <section class="jd-sec" aria-labelledby="g-channel">
                <div class="jd-sec-t"><h2 id="g-channel">Channel</h2></div>
                <div class="jd-sec-b"><dl class="jd-rows">
                    {{ $cell('Channel', $job->channel?->label()) }}
                    {{ $cell('Referral', $job->has_referral ? 'Ya' : 'Tidak') }}
                    @if ($job->has_referral)
                        {{ $cell('Nama referrer', $job->referrer_name, true) }}
                    @endif
                </dl></div>
            </section>

            {{-- Lokasi --}}
            <section class="jd-sec" aria-labelledby="g-lokasi">
                <div class="jd-sec-t"><h2 id="g-lokasi">Lokasi</h2></div>
                <div class="jd-sec-b"><dl class="jd-rows">
                    {{ $cell('Kota', $job->city) }}
                    {{ $cell('Mode kerja', $job->work_mode?->label()) }}
                </dl></div>
            </section>

            {{-- Penilaian (fit) --}}
            <section class="jd-sec jd-has-tip" aria-labelledby="g-fit"
                     x-data="{ open: null }"
                     @keydown.escape.window="open = null"
                     @click.outside="open = null">
                <div class="jd-sec-t"><h2 id="g-fit">Penilaian</h2></div>
                <div class="jd-sec-b"><dl class="jd-rows">
                    <dt class="jd-l">Kecocokan (fit)</dt>
                    <dd class="jd-v jd-tipcell">
                        <x-score-dots :value="$job->fit_score" />
                        <button type="button" class="jd-tipbtn"
                                @click="open = open === 'fit' ? null : 'fit'"
                                :aria-expanded="open === 'fit' ? 'true' : 'false'"
                                aria-controls="tip-fit"
                                aria-label="Penjelasan skala Kecocokan (fit)"><span aria-hidden="true">!</span></button>
                    </dd>
                    <dt class="jd-l">Skill match</dt>
                    <dd class="jd-v jd-tipcell">
                        <x-score-dots :value="$job->skill_match_score" />
                        <button type="button" class="jd-tipbtn"
                                @click="open = open === 'skill' ? null : 'skill'"
                                :aria-expanded="open === 'skill' ? 'true' : 'false'"
                                aria-controls="tip-skill"
                                aria-label="Penjelasan skala Skill match"><span aria-hidden="true">!</span></button>
                    </dd>
                    {{ $cell('Kustomisasi CV', $job->cv_customization?->label(), true) }}
                </dl></div>

                <div id="tip-fit" class="jd-tip jd-tip-fit" role="region" aria-label="Penjelasan skala Kecocokan (fit)"
                     x-show="open === 'fit'" x-cloak x-transition.opacity.duration.150ms>
                    <p class="jd-tip-t">Kecocokan (fit), skala 1–5</p>
                    <p class="jd-tip-m">Seberapa cocok loker ini dengan minat, kultur, dan arah kariermu.</p>
                    <table>
                        <thead><tr><th scope="col">Skor</th><th scope="col">Artinya</th></tr></thead>
                        <tbody>
                        <tr><td>5</td><td>Sangat cocok</td></tr>
                        <tr><td>4</td><td>Cocok</td></tr>
                        <tr><td>3</td><td>Cukup cocok</td></tr>
                        <tr><td>2</td><td>Kurang cocok</td></tr>
                        <tr><td>1</td><td>Tidak cocok</td></tr>
                        </tbody>
                    </table>
                </div>

                <div id="tip-skill" class="jd-tip jd-tip-skill" role="region" aria-label="Penjelasan skala Skill match"
                     x-show="open === 'skill'" x-cloak x-transition.opacity.duration.150ms>
                    <p class="jd-tip-t">Skill match, skala 1–5</p>
                    <p class="jd-tip-m">Seberapa banyak skill yang diminta lowongan sudah kamu kuasai.</p>
                    <table>
                        <thead><tr><th scope="col">Skor</th><th scope="col">Artinya</th></tr></thead>
                        <tbody>
                        <tr><td>5</td><td>Hampir semua sudah dikuasai</td></tr>
                        <tr><td>4</td><td>Sebagian besar sudah dikuasai</td></tr>
                        <tr><td>3</td><td>Sekitar separuh dikuasai</td></tr>
                        <tr><td>2</td><td>Hanya sebagian kecil dikuasai</td></tr>
                        <tr><td>1</td><td>Hampir belum ada yang dikuasai</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Gaji --}}
            <section class="jd-sec" aria-labelledby="g-gaji">
                <div class="jd-sec-t"><h2 id="g-gaji">Gaji</h2></div>
                <div class="jd-sec-b"><dl class="jd-rows">
                    {{ $cell('Range gaji di lowongan', $salaryRange, ! $isOffer) }}
                    @if ($isOffer)
                        {{ $cell('Gaji ditawarkan', $job->salary_offered !== null ? $rupiah($job->salary_offered) : '') }}
                    @endif
                </dl></div>
            </section>

            {{-- Hasil --}}
            <section class="jd-sec" aria-labelledby="g-hasil">
                <div class="jd-sec-t"><h2 id="g-hasil">Hasil</h2></div>
                <div class="jd-sec-b">
                    @if ($isOffer || $isRejected)
                        <dl class="jd-rows">
                            @if ($isOffer)
                                {{ $cell('Keputusan offer', $job->offer_decision?->label(), ! $isRejected) }}
                            @endif
                            @if ($isRejected)
                                <div id="alasan-penolakan" class="jd-contents">
                                    {{ $cell('Alasan penolakan', $job->rejection_reason?->label(), ! $isOffer) }}
                                </div>
                            @endif
                        </dl>
                    @else
                        <div class="jd-rows"><p class="jd-row jd-empty">Belum ada hasil yang perlu dicatat untuk status ini.</p></div>
                    @endif
                </div>
            </section>

            {{-- Skill yang kurang --}}
            <section class="jd-sec" aria-labelledby="g-skill">
                <div class="jd-sec-t"><h2 id="g-skill">Skill yang kurang</h2></div>
                <div class="jd-sec-b"><div class="jd-rows">
                    @if ($job->skillGaps->isEmpty())
                        <p class="jd-row jd-empty">Belum ada skill yang dicatat.</p>
                    @else
                        <ul class="jd-row flex flex-wrap gap-2">
                            @foreach ($job->skillGaps as $gap)
                                <li class="jd-chip rounded-field bg-offwhite">{{ $gap->skill_name }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div></div>
            </section>

            {{-- Catatan --}}
            <section class="jd-sec" aria-labelledby="g-catatan">
                <div class="jd-sec-t"><h2 id="g-catatan">Catatan</h2></div>
                <div class="jd-sec-b"><div class="jd-rows">
                    @if (filled($job->notes))
                        <p class="jd-row jd-note whitespace-pre-line break-words">{{ $job->notes }}</p>
                    @else
                        <p class="jd-row jd-empty">Belum ada catatan.</p>
                    @endif
                </div></div>
            </section>

            {{-- Riwayat status --}}
            <section class="jd-sec" aria-labelledby="g-riwayat">
                <div class="jd-sec-t"><h2 id="g-riwayat">Riwayat Status</h2></div>
                <div class="jd-sec-b"><div class="jd-rows">
                    <div class="jd-row">
                        @if ($timeline->isEmpty())
                            <p class="jd-empty">Belum ada riwayat status.</p>
                        @else
                            <x-status-timeline :entries="$timeline" />
                        @endif
                    </div>
                </div></div>
            </section>

            {{-- Ubah status --}}
            <section class="jd-sec" aria-labelledby="g-ubah-status">
                <div class="jd-sec-t"><h2 id="g-ubah-status">Ubah Status</h2></div>
                <form method="POST"
                      action="{{ route('jobs.status', $job) }}"
                      class="jd-sec-b"
                      x-data="{ status: @js(old('status', '')) }">
                    @csrf
                    @method('PATCH')

                    <div class="jd-rows">
                        <p class="jd-row jd-meta">
                            Status saat ini: <span class="font-semibold text-obsidian">{{ $status->label() }}</span>
                        </p>

                        <label for="status" class="jd-l">Status baru</label>
                        <div class="jd-v jd-in">
                            <select id="status" name="status" x-model="status" required class="{{ $field('status') }}">
                                <option value="">Pilih status</option>
                                @foreach ($optionsOf(\App\Enums\JobStatus::class) as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <p class="mt-1 text-xs text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <label for="changed_at" class="jd-l">Tanggal perubahan</label>
                        <div class="jd-v jd-in">
                            <input id="changed_at" type="date" name="changed_at" required
                                   value="{{ old('changed_at', now()->toDateString()) }}"
                                   min="{{ $job->applied_date->toDateString() }}"
                                   max="{{ now()->toDateString() }}"
                                   class="{{ $field('changed_at') }}">
                            @error('changed_at')
                                <p class="mt-1 text-xs text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Hanya relevan untuk Rejected --}}
                        <div x-show="status === 'rejected'" x-cloak class="jd-contents">
                            <label for="rejection_reason" class="jd-l">Alasan penolakan (opsional)</label>
                            <div class="jd-v jd-in jd-w3">
                                <select id="rejection_reason" name="rejection_reason" class="{{ $field('rejection_reason') }}">
                                    <option value="">Belum diketahui</option>
                                    @foreach ($optionsOf(\App\Enums\RejectionReason::class) as $value => $label)
                                        <option value="{{ $value }}" @selected(old('rejection_reason') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('rejection_reason')
                                    <p class="mt-1 text-xs text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Hanya relevan untuk Offer --}}
                        <div x-show="status === 'offer'" x-cloak class="jd-contents">
                            <label for="offer_decision" class="jd-l">Keputusan offer (opsional)</label>
                            <div class="jd-v jd-in">
                                <select id="offer_decision" name="offer_decision" class="{{ $field('offer_decision') }}">
                                    <option value="">Belum ditentukan</option>
                                    @foreach ($optionsOf(\App\Enums\OfferDecision::class) as $value => $label)
                                        <option value="{{ $value }}" @selected(old('offer_decision') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('offer_decision')
                                    <p class="mt-1 text-xs text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <label for="salary_offered" class="jd-l">Gaji ditawarkan, dalam Rupiah (opsional)</label>
                            <div class="jd-v jd-in">
                                <input id="salary_offered" type="text" name="salary_offered" inputmode="numeric"
                                       value="{{ old('salary_offered') }}" placeholder="Contoh: 8000000"
                                       class="{{ $field('salary_offered') }}">
                                @error('salary_offered')
                                    <p class="mt-1 text-xs text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <p x-show="status === 'ghosted'" x-cloak class="jd-row jd-hintcell">
                            Pilih Ghosted kalau perusahaan tidak memberi kabar sama sekali.
                        </p>
                        <p class="jd-row jd-hintcell">
                            Ubah tanggalnya kalau kamu mencatat kejadian yang sudah lewat.
                        </p>

                        <div class="jd-row jd-act"><x-button type="submit">Simpan Status</x-button></div>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-layouts.app>