<x-layouts.app title="Loker">
    @php
        // Route tambah dan detail belum dibuat: pakai '#' dulu, otomatis aktif saat route-nya ada
        $createUrl = Route::has('jobs.create') ? route('jobs.create') : '#';
        $hasDetailRoute = Route::has('jobs.show');

        $columns = [
            'company_name' => 'Perusahaan',
            'position' => 'Posisi',
            'current_status' => 'Status',
            'applied_date' => 'Tanggal Apply',
            'channel' => 'Channel',
            'city' => 'Kota',
        ];

        // Klik judul kolom: urut naik, klik lagi: urut turun. Pencarian dan filter tetap terbawa.
        $sortUrl = function (string $column) use ($sort, $dir) {
            $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';

            return request()->fullUrlWithQuery(['sort' => $column, 'dir' => $nextDir, 'page' => null]);
        };

        // Kelas bersama (token dari tailwind.config.js)
        $inputClass = 'h-10 w-full min-w-0 rounded-field border border-nude bg-offwhite px-3 text-obsidian placeholder:text-obsidian/40 focus:border-obsidian focus:outline-none focus:ring-1 focus:ring-obsidian';
        $btnBase = 'inline-flex h-btn items-center justify-center whitespace-nowrap rounded-field px-4 font-semibold focus:outline-none focus-visible:ring-1 focus-visible:ring-obsidian focus-visible:ring-offset-2 focus-visible:ring-offset-offwhite disabled:cursor-not-allowed disabled:opacity-40';
        $btnPrimary = $btnBase.' bg-obsidian text-offwhite hover:opacity-90';
        $btnOutline = $btnBase.' border border-obsidian text-obsidian hover:bg-ivory';
        $btnLink = 'font-semibold text-obsidian/70 underline-offset-4 hover:text-obsidian hover:underline focus:outline-none focus-visible:underline disabled:cursor-not-allowed disabled:opacity-30 disabled:no-underline';
    @endphp

    <h1 class="sr-only">Loker</h1>

    <div class="space-y-6">
        {{-- Tambah Loker --}}
        <div class="flex justify-end">
            <a href="{{ $createUrl }}" class="{{ $btnPrimary }}">Tambah Loker</a>
        </div>

        {{-- Pencarian + filter builder --}}
        <form method="GET"
              action="{{ route('jobs.index') }}"
              autocomplete="off"
              x-data="jobFilter(@js($filterConfig))"
              class="rounded-card border border-nude bg-ivory p-4 shadow-card">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="dir" value="{{ $dir }}">

            <div class="flex flex-col gap-2 sm:flex-row">
                <label for="q" class="sr-only">Cari loker</label>
                <input id="q" type="search" name="q" value="{{ $search }}" maxlength="100"
                       placeholder="Cari perusahaan atau posisi"
                       class="{{ $inputClass }} sm:flex-1">

                <button type="button" @click="toggle()"
                        :aria-expanded="open.toString()" aria-controls="panel-filter"
                        class="{{ $btnOutline }}"
                        x-text="rules.length ? 'Filter (' + rules.length + ')' : 'Filter'">Filter</button>

                <button type="submit" class="{{ $btnPrimary }}">Terapkan</button>

                @if ($hasFilter)
                    <a href="{{ route('jobs.index') }}" class="{{ $btnBase }} {{ $btnLink }}">Reset</a>
                @endif
            </div>

            <div id="panel-filter" x-show="open" x-cloak class="mt-4 space-y-3 border-t border-nude pt-4">
                <div x-show="rules.length > 1" class="flex flex-wrap items-center gap-2">
                    <label for="match" class="font-semibold">Tampilkan loker yang cocok dengan</label>
                    <select id="match" name="match" x-model="match" class="{{ $inputClass }} !w-auto">
                        <option value="all">semua kondisi</option>
                        <option value="any">salah satu kondisi</option>
                    </select>
                </div>

                <p x-show="rules.length === 0" class="text-obsidian/70">
                    Belum ada kondisi. Tambahkan kondisi untuk memilih atribut yang mau difilter.
                </p>

                <template x-for="(rule, i) in rules" :key="rule.id">
                    <div class="grid gap-2 rounded-field border border-nude bg-offwhite p-3 lg:grid-cols-[13rem_10rem_minmax(0,1fr)_auto] lg:items-start lg:border-0 lg:bg-transparent lg:p-0">
                        {{-- 1. Atribut --}}
                        <select :name="'filter[' + i + '][field]'"
                                :aria-label="'Atribut kondisi ' + (i + 1)"
                                x-model="rule.field"
                                @change="changeField(rule, $event.target.value)"
                                class="{{ $inputClass }}">
                            <template x-for="f in fieldList" :key="f.key">
                                <option :value="f.key" x-text="f.label" :selected="f.key === rule.field"></option>
                            </template>
                        </select>

                        {{-- 2. Operator --}}
                        <select :name="'filter[' + i + '][op]'"
                                :aria-label="'Operator kondisi ' + (i + 1)"
                                x-model="rule.op"
                                class="{{ $inputClass }}">
                            <template x-for="o in opsFor(rule)" :key="o.value">
                                <option :value="o.value" x-text="o.label" :selected="o.value === rule.op"></option>
                            </template>
                        </select>

                        {{-- 3. Nilai (menyesuaikan tipe atribut) --}}
                        <div class="flex min-w-0 flex-col gap-2 sm:flex-row">
                            <template x-if="inputs(rule) >= 1 && type(rule) === 'enum'">
                                <select :name="'filter[' + i + '][value]'" :aria-label="'Nilai kondisi ' + (i + 1)"
                                        x-model="rule.value" class="{{ $inputClass }}">
                                    <option value="" :selected="rule.value === ''">Pilih salah satu</option>
                                    <template x-for="o in options(rule)" :key="o.value">
                                        <option :value="o.value" x-text="o.label" :selected="o.value === rule.value"></option>
                                    </template>
                                </select>
                            </template>

                            <template x-if="inputs(rule) >= 1 && type(rule) === 'boolean'">
                                <select :name="'filter[' + i + '][value]'" :aria-label="'Nilai kondisi ' + (i + 1)"
                                        x-model="rule.value" class="{{ $inputClass }}">
                                    <option value="1" :selected="rule.value === '1'">Ya</option>
                                    <option value="0" :selected="rule.value === '0'">Tidak</option>
                                </select>
                            </template>

                            <template x-if="inputs(rule) >= 1 && type(rule) === 'date'">
                                <input type="date" :name="'filter[' + i + '][value]'" :aria-label="'Tanggal kondisi ' + (i + 1)"
                                       x-model="rule.value" class="{{ $inputClass }}">
                            </template>

                            <template x-if="inputs(rule) >= 1 && type(rule) === 'number'">
                                <input type="number" min="0" inputmode="numeric" placeholder="Angka"
                                       :name="'filter[' + i + '][value]'" :aria-label="'Angka kondisi ' + (i + 1)"
                                       x-model="rule.value" class="{{ $inputClass }}">
                            </template>

                            <template x-if="inputs(rule) >= 1 && (type(rule) === 'text' || type(rule) === 'skill')">
                                <input type="text" maxlength="150" placeholder="Ketik kata kunci"
                                       :name="'filter[' + i + '][value]'" :aria-label="'Kata kunci kondisi ' + (i + 1)"
                                       x-model="rule.value" class="{{ $inputClass }}">
                            </template>

                            {{-- Nilai kedua untuk operator "antara" --}}
                            <template x-if="inputs(rule) === 2 && type(rule) === 'date'">
                                <input type="date" :name="'filter[' + i + '][value2]'" :aria-label="'Sampai tanggal kondisi ' + (i + 1)"
                                       x-model="rule.value2" class="{{ $inputClass }}">
                            </template>

                            <template x-if="inputs(rule) === 2 && type(rule) === 'number'">
                                <input type="number" min="0" inputmode="numeric" placeholder="Sampai"
                                       :name="'filter[' + i + '][value2]'" :aria-label="'Sampai angka kondisi ' + (i + 1)"
                                       x-model="rule.value2" class="{{ $inputClass }}">
                            </template>
                        </div>

                        {{-- 4. Hapus kondisi --}}
                        <button type="button" @click="remove(i)" aria-label="Hapus kondisi"
                                class="inline-flex h-10 w-10 items-center justify-center justify-self-end rounded-field border border-nude hover:bg-ivory focus:outline-none focus-visible:ring-1 focus-visible:ring-obsidian lg:justify-self-auto">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </div>
                </template>

                <div>
                    <button type="button" @click="add()" :disabled="rules.length >= max" class="{{ $btnLink }}">
                        + Tambah kondisi
                    </button>
                </div>
            </div>
        </form>

        {{-- Tabel atau empty state --}}
        @if ($jobs->isEmpty())
            <div class="rounded-card border border-nude bg-ivory px-6 py-12 text-center shadow-card">
                @if ($hasAnyJobs)
                    <h2>Tidak ada loker yang cocok</h2>
                    <p class="mx-auto mt-2 max-w-sm text-obsidian/70">
                        Coba ganti kata kunci atau longgarkan kondisi filter.
                    </p>
                    <a href="{{ route('jobs.index') }}" class="{{ $btnOutline }} mt-6">Reset pencarian dan filter</a>
                @else
                    <h2>Belum ada loker</h2>
                    <p class="mx-auto mt-2 max-w-sm text-obsidian/70">
                        Catat lamaran pertamamu supaya progresnya bisa dilacak di sini.
                    </p>
                    <a href="{{ $createUrl }}" class="{{ $btnPrimary }} mt-6">Tambah Loker</a>
                @endif
            </div>
        @else
            <div class="overflow-hidden rounded-card border border-nude bg-ivory shadow-card">
                {{-- Di layar kecil tabel bisa di-scroll ke samping --}}
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-left text-sm">
                        <thead class="border-b border-nude">
                            <tr>
                                <th scope="col" class="w-12 whitespace-nowrap px-4 py-3 font-semibold">No</th>
                                @foreach ($columns as $key => $label)
                                    @php $isSorted = $sort === $key; @endphp
                                    <th scope="col"
                                        aria-sort="{{ $isSorted ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                                        class="whitespace-nowrap px-4 py-3 font-semibold">
                                        <a href="{{ $sortUrl($key) }}"
                                           class="inline-flex items-center gap-1 underline-offset-4 hover:underline focus:outline-none focus-visible:underline">
                                            {{ $label }}
                                            <span aria-hidden="true" class="{{ $isSorted ? 'text-obsidian' : 'text-obsidian/30' }}">
                                                {{ $isSorted ? ($dir === 'asc' ? '↑' : '↓') : '↕' }}
                                            </span>
                                        </a>
                                    </th>
                                @endforeach
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-nude">
                            @foreach ($jobs as $job)
                                <tr class="hover:bg-offwhite">
                                    <td class="whitespace-nowrap px-4 py-3 text-obsidian/60">
                                        {{ $jobs->firstItem() + $loop->index }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold">
                                        <span class="block max-w-[14rem] truncate" title="{{ $job->company_name }}">{{ $job->company_name }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="block max-w-[14rem] truncate" title="{{ $job->position }}">{{ $job->position }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-status-badge :status="$job->current_status" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        {{ $job->applied_date->locale('id')->translatedFormat('d M Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ $job->channel?->label() ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $job->city ?: '-' }}</td>
                                    <td class="px-4 py-3 text-right">
    <a href="{{ route('jobs.show', $job) }}" class="{{ $btnOutline }}">Lihat Detail</a>
</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                <p class="text-xs text-obsidian/60">
                    Menampilkan {{ $jobs->firstItem() }}-{{ $jobs->lastItem() }} dari {{ $jobs->total() }} loker
                </p>
                {{ $jobs->links('vendor.pagination.jobtracker') }}
            </div>
        @endif
    </div>

    {{-- Komponen Alpine untuk filter builder --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('jobFilter', (config) => {
                let uid = 0;
                const fields = config.fields;
                const operators = config.operators;

                // Baris kondisi baru untuk atribut tertentu
                const blank = (field) => {
                    const type = fields[field].type;

                    return {
                        field,
                        op: operators[type][0].value,
                        value: type === 'boolean' ? '1' : '',
                        value2: '',
                    };
                };

                return {
                    fields,
                    operators,
                    fieldList: Object.entries(fields).map(([key, f]) => ({ key, label: f.label })),
                    max: config.max,
                    match: config.match,
                    open: config.rules.length > 0,
                    rules: config.rules.map((r) => ({
                        id: ++uid,
                        field: r.field,
                        op: r.op,
                        value: r.value ?? '',
                        value2: r.value2 ?? '',
                    })),

                    type(rule) {
                        return this.fields[rule.field].type;
                    },
                    opsFor(rule) {
                        return this.operators[this.type(rule)];
                    },
                    inputs(rule) {
                        const op = this.opsFor(rule).find((o) => o.value === rule.op);

                        return op ? op.inputs : 0;
                    },
                    options(rule) {
                        return this.fields[rule.field].options || [];
                    },
                    toggle() {
                        this.open = !this.open;

                        if (this.open && this.rules.length === 0) {
                            this.add();
                        }
                    },
                    add() {
                        if (this.rules.length >= this.max) {
                            return;
                        }

                        this.rules.push({ id: ++uid, ...blank(this.fieldList[0].key) });
                        this.open = true;
                    },
                    remove(index) {
                        this.rules.splice(index, 1);
                    },
                    changeField(rule, field) {
                        Object.assign(rule, blank(field));
                    },
                };
            });
        });
    </script>
</x-layouts.app>