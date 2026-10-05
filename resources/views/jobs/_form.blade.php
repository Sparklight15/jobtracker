{{--
    Form loker yang dipakai bersama halaman Tambah dan Edit.

    Variabel:
      $job         Job|null  null = mode Tambah, Job = mode Edit (isian awal diambil dari sini)
      $action      string    URL tujuan form
      $method      string    'POST' atau 'PUT'
      $submitLabel string    teks tombol simpan
      $cancelUrl   string    tujuan tombol Batal
--}}
@php
    $isEdit = $job !== null;
    $today = now()->toDateString();

    $card = 'rounded-card border border-nude bg-ivory p-6 shadow-card';

    // Kelas input (token dari tailwind.config.js). Border merah tipis hanya saat error.
    $inputBase = 'h-10 w-full min-w-0 rounded-field border bg-offwhite px-3 text-obsidian placeholder:text-obsidian/40 focus:border-obsidian focus:outline-none focus:ring-1 focus:ring-obsidian';
    $field = fn (string $name) => $inputBase.' '.($errors->has($name) ? 'border-error' : 'border-nude');
    $area = fn (string $name) => str_replace('h-10', 'h-28 resize-y py-2', $field($name));

    // Daftar [nilai => label] dari enum untuk <select>
    $optionsOf = fn (string $enum) => collect($enum::cases())
        ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
        ->all();

    // Nilai awal dari loker (Edit), dinormalkan ke bentuk yang cocok untuk atribut HTML
    $jobValue = function (string $name) use ($job) {
        $value = $job?->{$name};

        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        };
    };

    // Isian lama (setelah validasi gagal) menang atas data loker, yang menang atas bawaan
    $val = fn (string $name, $default = null) => old($name, $jobValue($name) ?? $default);

    // Status saat ini di database (Edit) atau Applied (Tambah). Tanggal perubahan status
    // hanya diminta kalau pilihan status berbeda dari ini.
    $baseStatus = $isEdit ? $job->current_status->value : 'applied';
    $statusValue = old('status', $baseStatus);

    // Skill: setelah validasi gagal pakai isian lama (termasuk daftar kosong, supaya skill
    // yang sudah dihapus tidak muncul lagi), kalau tidak pakai skill di database
    $initialSkills = session()->hasOldInput()
        ? array_values(array_filter((array) old('skills', []), 'is_string'))
        : ($isEdit ? $job->skillGaps->pluck('skill_name')->all() : []);

    // Tanda field wajib: bintang untuk mata, "(wajib)" untuk pembaca layar
    $req = '<span class="text-error" aria-hidden="true">*</span><span class="sr-only"> (wajib)</span>';

    $skillErrors = collect($errors->get('skills.*'))->flatten()->unique()->all();
@endphp

@if ($errors->any())
    <div role="alert" class="rounded-field border border-error bg-ivory px-4 py-3 font-semibold text-error">
        Ada isian yang perlu diperbaiki. Periksa kolom yang ditandai di bawah.
    </div>
@endif

<form method="POST"
      action="{{ $action }}"
      class="space-y-6"
      x-data="{
          status: @js($statusValue),
          baseStatus: @js($baseStatus),
          appliedDate: @js(old('applied_date', $jobValue('applied_date') ?? $today)),
          hasReferral: @js((bool) $val('has_referral', false)),
          skills: @js($initialSkills),
          draft: '',
          addSkill() {
              const value = this.draft.replace(/\s+/g, ' ').trim().slice(0, 100);
              this.draft = '';
              if (value === '' || this.skills.length >= 20) return;
              if (this.skills.some(s => s.toLowerCase() === value.toLowerCase())) return;
              this.skills.push(value);
          },
          removeSkill(index) {
              this.skills.splice(index, 1);
          },
      }">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Identitas --}}
        <section class="{{ $card }}" aria-labelledby="g-identitas">
            <h2 id="g-identitas">Identitas</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="company_name" class="font-semibold">Perusahaan {!! $req !!}</label>
                    <input id="company_name" type="text" name="company_name" required maxlength="150"
                           value="{{ $val('company_name') }}" class="mt-1 {{ $field('company_name') }}">
                    @error('company_name')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0">
                    <label for="position" class="font-semibold">Posisi {!! $req !!}</label>
                    <input id="position" type="text" name="position" required maxlength="150"
                           value="{{ $val('position') }}" class="mt-1 {{ $field('position') }}">
                    @error('position')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0">
                    <label for="industry_sector" class="font-semibold">Sektor industri</label>
                    <select id="industry_sector" name="industry_sector" class="mt-1 {{ $field('industry_sector') }}">
                        <option value="">Belum diketahui</option>
                        @foreach ($optionsOf(\App\Enums\IndustrySector::class) as $value => $label)
                            <option value="{{ $value }}" @selected($val('industry_sector') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('industry_sector')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0 sm:col-span-2">
                    <label for="job_url" class="font-semibold">Link lowongan</label>
                    <input id="job_url" type="text" name="job_url" inputmode="url" maxlength="500"
                           value="{{ $val('job_url') }}" placeholder="https://..."
                           class="mt-1 {{ $field('job_url') }}">
                    @error('job_url')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- Waktu dan status --}}
        <section class="{{ $card }}" aria-labelledby="g-waktu">
            <h2 id="g-waktu">Waktu dan Status</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="applied_date" class="font-semibold">Tanggal apply {!! $req !!}</label>
                    <input id="applied_date" type="date" name="applied_date" required
                           x-model="appliedDate" max="{{ $today }}"
                           value="{{ old('applied_date', $jobValue('applied_date') ?? $today) }}"
                           class="mt-1 {{ $field('applied_date') }}">
                    @error('applied_date')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0">
                    <label for="status" class="font-semibold">Status {!! $req !!}</label>
                    <select id="status" name="status" required x-model="status" class="mt-1 {{ $field('status') }}">
                        @foreach ($optionsOf(\App\Enums\JobStatus::class) as $value => $label)
                            <option value="{{ $value }}" @selected($statusValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Hanya relevan kalau status berbeda dari status awal/saat ini --}}
                <div class="min-w-0 sm:col-span-2" x-show="status !== baseStatus" x-cloak>
                    <label for="changed_at" class="font-semibold">Tanggal status berubah {!! $req !!}</label>
                    <input id="changed_at" type="date" name="changed_at"
                           :required="status !== baseStatus" :disabled="status === baseStatus"
                           :min="appliedDate" max="{{ $today }}"
                           value="{{ old('changed_at') ?: $today }}"
                           class="mt-1 {{ $field('changed_at') }}">
                    @error('changed_at')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-obsidian/60">
                        @if ($isEdit)
                            Perubahan status ditambahkan sebagai baris baru di riwayat pada tanggal ini.
                        @else
                            Riwayat awal "Applied" otomatis dicatat pada tanggal apply.
                        @endif
                    </p>
                </div>

                {{-- Hanya relevan untuk Rejected --}}
                <div class="min-w-0 sm:col-span-2" x-show="status === 'rejected'" x-cloak>
                    <label for="rejection_reason" class="font-semibold">Alasan penolakan (opsional)</label>
                    <select id="rejection_reason" name="rejection_reason" :disabled="status !== 'rejected'"
                            class="mt-1 {{ $field('rejection_reason') }}">
                        <option value="">Belum diketahui</option>
                        @foreach ($optionsOf(\App\Enums\RejectionReason::class) as $value => $label)
                            <option value="{{ $value }}" @selected($val('rejection_reason') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('rejection_reason')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Hanya relevan untuk Offer --}}
                <div class="min-w-0" x-show="status === 'offer'" x-cloak>
                    <label for="offer_decision" class="font-semibold">Keputusan offer (opsional)</label>
                    <select id="offer_decision" name="offer_decision" :disabled="status !== 'offer'"
                            class="mt-1 {{ $field('offer_decision') }}">
                        <option value="">Belum ditentukan</option>
                        @foreach ($optionsOf(\App\Enums\OfferDecision::class) as $value => $label)
                            <option value="{{ $value }}" @selected($val('offer_decision') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('offer_decision')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0" x-show="status === 'offer'" x-cloak>
                    <label for="salary_offered" class="font-semibold">Gaji ditawarkan, dalam Rupiah (opsional)</label>
                    <input id="salary_offered" type="text" name="salary_offered" inputmode="numeric"
                           :disabled="status !== 'offer'"
                           value="{{ $val('salary_offered') }}" placeholder="Contoh: 8000000"
                           class="mt-1 {{ $field('salary_offered') }}">
                    @error('salary_offered')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- Channel --}}
        <section class="{{ $card }}" aria-labelledby="g-channel">
            <h2 id="g-channel">Channel</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="min-w-0 sm:col-span-2">
                    <label for="channel" class="font-semibold">Channel {!! $req !!}</label>
                    <select id="channel" name="channel" required class="mt-1 {{ $field('channel') }}">
                        <option value="">Pilih channel</option>
                        @foreach ($optionsOf(\App\Enums\Channel::class) as $value => $label)
                            <option value="{{ $value }}" @selected($val('channel') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('channel')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0 sm:col-span-2">
                    <input type="hidden" name="has_referral" value="0">
                    <label class="inline-flex items-center gap-2 font-semibold">
                        <input type="checkbox" name="has_referral" value="1" x-model="hasReferral"
                               class="h-4 w-4 rounded-field border-nude text-obsidian focus:ring-obsidian">
                        Ada referral
                    </label>
                    @error('has_referral')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0 sm:col-span-2" x-show="hasReferral" x-cloak>
                    <label for="referrer_name" class="font-semibold">Nama referrer</label>
                    <input id="referrer_name" type="text" name="referrer_name" maxlength="100"
                           :disabled="!hasReferral"
                           value="{{ $val('referrer_name') }}" class="mt-1 {{ $field('referrer_name') }}">
                    @error('referrer_name')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- Lokasi --}}
        <section class="{{ $card }}" aria-labelledby="g-lokasi">
            <h2 id="g-lokasi">Lokasi</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="city" class="font-semibold">Kota</label>
                    <input id="city" type="text" name="city" maxlength="100"
                           value="{{ $val('city') }}" class="mt-1 {{ $field('city') }}">
                    @error('city')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0">
                    <label for="work_mode" class="font-semibold">Mode kerja</label>
                    <select id="work_mode" name="work_mode" class="mt-1 {{ $field('work_mode') }}">
                        <option value="">Belum diketahui</option>
                        @foreach ($optionsOf(\App\Enums\WorkMode::class) as $value => $label)
                            <option value="{{ $value }}" @selected($val('work_mode') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('work_mode')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- Gaji --}}
        <section class="{{ $card }}" aria-labelledby="g-gaji">
            <h2 id="g-gaji">Gaji</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="salary_min" class="font-semibold">Gaji minimum di lowongan, Rupiah</label>
                    <input id="salary_min" type="text" name="salary_min" inputmode="numeric"
                           value="{{ $val('salary_min') }}" placeholder="Contoh: 8000000"
                           class="mt-1 {{ $field('salary_min') }}">
                    @error('salary_min')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0">
                    <label for="salary_max" class="font-semibold">Gaji maksimum di lowongan, Rupiah</label>
                    <input id="salary_max" type="text" name="salary_max" inputmode="numeric"
                           value="{{ $val('salary_max') }}" placeholder="Contoh: 12000000"
                           class="mt-1 {{ $field('salary_max') }}">
                    @error('salary_max')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- Penilaian (fit) --}}
        <section class="{{ $card }}" aria-labelledby="g-fit">
            <h2 id="g-fit">Penilaian</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="fit_score" class="font-semibold">Kecocokan (fit)</label>
                    <select id="fit_score" name="fit_score" class="mt-1 {{ $field('fit_score') }}">
                        <option value="">Belum dinilai</option>
                        @foreach (range(1, 5) as $score)
                            <option value="{{ $score }}" @selected((string) $val('fit_score') === (string) $score)>{{ $score }} dari 5</option>
                        @endforeach
                    </select>
                    @error('fit_score')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0">
                    <label for="skill_match_score" class="font-semibold">Skill match</label>
                    <select id="skill_match_score" name="skill_match_score" class="mt-1 {{ $field('skill_match_score') }}">
                        <option value="">Belum dinilai</option>
                        @foreach (range(1, 5) as $score)
                            <option value="{{ $score }}" @selected((string) $val('skill_match_score') === (string) $score)>{{ $score }} dari 5</option>
                        @endforeach
                    </select>
                    @error('skill_match_score')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-0 sm:col-span-2">
                    <label for="cv_customization" class="font-semibold">Kustomisasi CV</label>
                    <select id="cv_customization" name="cv_customization" class="mt-1 {{ $field('cv_customization') }}">
                        <option value="">Belum diisi</option>
                        @foreach ($optionsOf(\App\Enums\CvCustomization::class) as $value => $label)
                            <option value="{{ $value }}" @selected($val('cv_customization') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('cv_customization')
                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- Skill yang kurang (input tag) --}}
        <section class="{{ $card }} lg:col-span-2" aria-labelledby="g-skill">
            <h2 id="g-skill">Skill yang kurang</h2>

            <div class="mt-4">
                <label for="skill_draft" class="font-semibold">Tambah skill</label>
                <div class="mt-1 flex gap-2">
                    <input id="skill_draft" type="text" maxlength="100" x-model="draft"
                           @keydown.enter.prevent="addSkill()"
                           @keydown.comma.prevent="addSkill()"
                           @blur="addSkill()"
                           placeholder="Ketik nama skill, lalu tekan Enter"
                           class="{{ $inputBase }} border-nude">
                    <button type="button" @click="addSkill()"
                            class="inline-flex h-10 shrink-0 items-center justify-center rounded-field border border-nude px-4 text-sm font-semibold transition hover:bg-offwhite focus:outline-none focus-visible:ring-1 focus-visible:ring-obsidian">
                        Tambah
                    </button>
                </div>
                <p class="mt-1 text-xs text-obsidian/60">Maksimal 20 skill. Skill yang sama hanya dicatat sekali.</p>

                @error('skills')
                    <p class="mt-1 text-xs text-error">{{ $message }}</p>
                @enderror
                @foreach ($skillErrors as $message)
                    <p class="mt-1 text-xs text-error">{{ $message }}</p>
                @endforeach
            </div>

            <ul class="mt-4 flex flex-wrap gap-2" x-show="skills.length > 0" x-cloak aria-label="Skill yang sudah ditambahkan">
                <template x-for="(skill, index) in skills" :key="skill">
                    <li class="inline-flex items-center gap-2 rounded-field border border-nude bg-offwhite px-3 py-1 text-sm">
                        <span class="break-words" x-text="skill"></span>
                        <input type="hidden" name="skills[]" :value="skill">
                        <button type="button" @click="removeSkill(index)"
                                :aria-label="'Hapus skill ' + skill"
                                class="font-semibold text-obsidian/70 hover:text-obsidian focus:outline-none focus-visible:ring-1 focus-visible:ring-obsidian">
                            <span aria-hidden="true">×</span>
                        </button>
                    </li>
                </template>
            </ul>
        </section>

        {{-- Catatan --}}
        <section class="{{ $card }} lg:col-span-2" aria-labelledby="g-catatan">
            <h2 id="g-catatan">Catatan</h2>
            <div class="mt-4">
                <label for="notes" class="sr-only">Catatan</label>
                <textarea id="notes" name="notes" maxlength="5000" class="{{ $area('notes') }}">{{ $val('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-xs text-error">{{ $message }}</p>
                @enderror
            </div>
        </section>
    </div>

    <div class="flex flex-wrap gap-2">
        <x-button type="submit">{{ $submitLabel }}</x-button>
        <x-button variant="outline" :href="$cancelUrl">Batal</x-button>
    </div>
</form>