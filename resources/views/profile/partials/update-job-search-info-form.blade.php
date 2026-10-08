<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Pengaturan Pencarian Kerja
        </h2>
        <p class="mt-1 text-sm text-gray-600">
            Atur tanggal mulai mencari kerja dan target lamaran Anda untuk menyesuaikan perhitungan statistik.
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <!-- Tanggal Mulai Job Search -->
        <div>
            <x-input-label for="job_search_started_at" value="Tanggal Mulai Mencari Kerja" />
            <x-text-input id="job_search_started_at" name="job_search_started_at" type="date" class="mt-1 block w-full" :value="old('job_search_started_at', $user->job_search_started_at?->format('Y-m-d'))" />
            <x-input-error class="mt-2" :messages="$errors->get('job_search_started_at')" />
        </div>

        <!-- Target Apply & Periode -->
        <div class="flex gap-4">
            <div class="flex-1">
                <x-input-label for="apply_target" value="Target Jumlah Apply" />
                <x-text-input id="apply_target" name="apply_target" type="number" min="1" class="mt-1 block w-full" :value="old('apply_target', $user->apply_target)" placeholder="Contoh: 10" />
                <x-input-error class="mt-2" :messages="$errors->get('apply_target')" />
            </div>

            <div class="flex-1">
                <x-input-label for="apply_target_period" value="Periode Target" />
                <select id="apply_target_period" name="apply_target_period" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="week" {{ old('apply_target_period', $user->apply_target_period?->value ?? 'week') === 'week' ? 'selected' : '' }}>Per Minggu</option>
                    <option value="month" {{ old('apply_target_period', $user->apply_target_period?->value ?? 'month') === 'month' ? 'selected' : '' }}>Per Bulan</option>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('apply_target_period')" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Simpan Pengaturan</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >Tersimpan.</p>
            @endif
        </div>
    </form>
</section>