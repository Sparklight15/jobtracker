<?php

namespace App\Http\Requests;

use App\Enums\Channel;
use App\Enums\CvCustomization;
use App\Enums\IndustrySector;
use App\Enums\JobStatus;
use App\Enums\OfferDecision;
use App\Enums\RejectionReason;
use App\Enums\WorkMode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Aturan validasi dan normalisasi yang dipakai bersama form Tambah (StoreJobRequest)
 * dan Edit (UpdateJobRequest). Perbedaannya hanya satu: kapan status dianggap berubah
 * (lihat wantsStatusChange()).
 */
abstract class JobFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route sudah dilindungi middleware auth. user_id tidak pernah dibaca dari request.
        return $this->user() !== null;
    }

    protected function selectedStatus(): ?JobStatus
    {
        return JobStatus::tryFrom((string) $this->input('status'));
    }

    /**
     * Apakah isian ini meminta perubahan status (dan butuh tanggal perubahan)?
     * Tambah: status awal selain Applied. Edit: status berbeda dari status saat ini.
     */
    protected function wantsStatusChange(): bool
    {
        $status = $this->selectedStatus();

        return $status !== null && $status !== JobStatus::Applied;
    }

    protected function prepareForValidation(): void
    {
        $status = $this->selectedStatus();
        $merge = [];

        // Uang boleh diketik dengan pemisah ribuan ("15.000.000"). Karakter lain dibiarkan
        // supaya ditolak oleh aturan integer.
        foreach (['salary_min', 'salary_max', 'salary_offered'] as $key) {
            if ($this->has($key)) {
                $merge[$key] = $this->normalizeMoney($this->input($key));
            }
        }

        // Isian yang tidak relevan untuk status yang dipilih diabaikan, bukan ditolak.
        // Select yang disembunyikan di browser tetap ikut terkirim, jadi ini perlu.
        if ($status !== JobStatus::Rejected) {
            $merge['rejection_reason'] = null;
        }

        if ($status !== JobStatus::Offer) {
            $merge['offer_decision'] = null;
            $merge['salary_offered'] = null;
        }

        if (! $this->wantsStatusChange()) {
            $merge['changed_at'] = null;
        }

        // Checkbox: nilai yang tidak ada atau "0" berarti tidak dicentang
        $hasReferral = $this->boolean('has_referral');
        $merge['has_referral'] = $hasReferral;

        if (! $hasReferral) {
            $merge['referrer_name'] = null;
        }

        // Skill: rapikan spasi, buang yang kosong, buang duplikat (huruf besar-kecil dianggap sama)
        $skills = $this->input('skills');

        if (is_array($skills)) {
            $seen = [];
            $clean = [];

            foreach ($skills as $skill) {
                if ($skill === null) {
                    continue;
                }

                if (! is_string($skill)) {
                    // Biarkan aturan skills.* yang menolaknya
                    $clean[] = $skill;

                    continue;
                }

                $skill = trim((string) preg_replace('/\s+/u', ' ', $skill));
                $key = mb_strtolower($skill);

                if ($skill === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $clean[] = $skill;
            }

            $merge['skills'] = $clean;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $today = now()->toDateString();

        return [
            // Identitas
            'company_name' => ['required', 'string', 'max:150'],
            'position' => ['required', 'string', 'max:150'],
            'job_url' => ['nullable', 'string', 'max:500', 'regex:#^https?://\S+$#i'],
            'industry_sector' => ['nullable', Rule::enum(IndustrySector::class)],

            // Waktu dan status
            'applied_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'status' => ['required', Rule::enum(JobStatus::class)],
            'changed_at' => [
                Rule::requiredIf(fn () => $this->wantsStatusChange()),
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:'.$today,
            ],
            'rejection_reason' => ['nullable', Rule::enum(RejectionReason::class)],
            'offer_decision' => ['nullable', Rule::enum(OfferDecision::class)],
            'salary_offered' => ['nullable', 'integer', 'min:0', 'max:999999999999'],

            // Channel
            'channel' => ['required', Rule::enum(Channel::class)],
            'has_referral' => ['boolean'],
            'referrer_name' => ['nullable', 'string', 'max:100'],

            // Lokasi
            'city' => ['nullable', 'string', 'max:100'],
            'work_mode' => ['nullable', Rule::enum(WorkMode::class)],

            // Gaji di lowongan
            'salary_min' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'max:999999999999'],

            // Penilaian
            'fit_score' => ['nullable', 'integer', 'between:1,5'],
            'skill_match_score' => ['nullable', 'integer', 'between:1,5'],
            'cv_customization' => ['nullable', Rule::enum(CvCustomization::class)],

            // Skill yang kurang dan catatan
            'skills' => ['nullable', 'array', 'max:20'],
            'skills.*' => ['string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'company_name.max' => 'Nama perusahaan maksimal 150 karakter.',
            'position.required' => 'Posisi wajib diisi.',
            'position.max' => 'Posisi maksimal 150 karakter.',
            'job_url.regex' => 'Link lowongan harus diawali http:// atau https://.',
            'job_url.max' => 'Link lowongan maksimal 500 karakter.',
            'industry_sector.enum' => 'Sektor industri tidak valid.',

            'applied_date.required' => 'Tanggal apply wajib diisi.',
            'applied_date.date_format' => 'Format tanggal tidak valid.',
            'applied_date.before_or_equal' => 'Tanggal apply tidak boleh di masa depan.',
            'status.required' => 'Pilih status.',
            'status.enum' => 'Status yang dipilih tidak valid.',
            'changed_at.required' => 'Tanggal status berubah wajib diisi.',
            'changed_at.date_format' => 'Format tanggal tidak valid.',
            'changed_at.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'rejection_reason.enum' => 'Alasan penolakan tidak valid.',
            'offer_decision.enum' => 'Keputusan offer tidak valid.',
            'salary_offered.integer' => 'Gaji ditawarkan harus berupa angka.',
            'salary_offered.min' => 'Gaji ditawarkan tidak boleh negatif.',
            'salary_offered.max' => 'Gaji ditawarkan terlalu besar.',

            'channel.required' => 'Pilih channel.',
            'channel.enum' => 'Channel yang dipilih tidak valid.',
            'has_referral.boolean' => 'Isian referral tidak valid.',
            'referrer_name.max' => 'Nama referrer maksimal 100 karakter.',

            'city.max' => 'Kota maksimal 100 karakter.',
            'work_mode.enum' => 'Mode kerja tidak valid.',

            'salary_min.integer' => 'Gaji minimum harus berupa angka.',
            'salary_min.min' => 'Gaji minimum tidak boleh negatif.',
            'salary_min.max' => 'Gaji minimum terlalu besar.',
            'salary_max.integer' => 'Gaji maksimum harus berupa angka.',
            'salary_max.min' => 'Gaji maksimum tidak boleh negatif.',
            'salary_max.max' => 'Gaji maksimum terlalu besar.',

            'fit_score.integer' => 'Kecocokan harus berupa angka 1 sampai 5.',
            'fit_score.between' => 'Kecocokan harus antara 1 sampai 5.',
            'skill_match_score.integer' => 'Skill match harus berupa angka 1 sampai 5.',
            'skill_match_score.between' => 'Skill match harus antara 1 sampai 5.',
            'cv_customization.enum' => 'Kustomisasi CV tidak valid.',

            'skills.array' => 'Daftar skill tidak valid.',
            'skills.max' => 'Skill yang kurang maksimal 20.',
            'skills.*.string' => 'Nama skill tidak valid.',
            'skills.*.max' => 'Nama skill maksimal 100 karakter.',
            'notes.max' => 'Catatan maksimal 5000 karakter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $errors = $validator->errors();

            // Gaji maksimum tidak boleh kurang dari minimum
            $min = $data['salary_min'] ?? null;
            $max = $data['salary_max'] ?? null;

            if ($min !== null && $max !== null && ! $errors->hasAny(['salary_min', 'salary_max']) && (int) $max < (int) $min) {
                $errors->add('salary_max', 'Gaji maksimum tidak boleh kurang dari gaji minimum.');
            }

            // Tanggal status berubah tidak boleh sebelum tanggal apply
            $applied = $data['applied_date'] ?? null;
            $changed = $data['changed_at'] ?? null;

            if ($applied && $changed && ! $errors->hasAny(['applied_date', 'changed_at']) && $changed < $applied) {
                $errors->add('changed_at', 'Tanggal tidak boleh sebelum tanggal apply.');
            }
        });
    }

    private function normalizeMoney(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[\d.,\s]+$/', $value)) {
            $digits = preg_replace('/\D/', '', $value);

            return $digits === '' ? null : $digits;
        }

        return $value;
    }
}