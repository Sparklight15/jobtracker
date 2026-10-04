<?php

namespace App\Http\Requests;

use App\Enums\JobStatus;
use App\Enums\OfferDecision;
use App\Enums\RejectionReason;
use App\Models\Job;
use App\Support\JobStatusChanger;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Bukan pemilik: JobPolicy menghasilkan 404, dicek sebelum validasi apa pun
        Gate::authorize('update', $this->route('job'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        // Gaji boleh diketik dengan pemisah ribuan ("15.000.000"). Karakter lain dibiarkan
        // supaya ditolak oleh aturan integer.
        $salary = $this->input('salary_offered');

        if (is_string($salary) && preg_match('/^[\d.,\s]+$/', $salary)) {
            $digits = preg_replace('/\D/', '', $salary);

            $this->merge(['salary_offered' => $digits === '' ? null : $digits]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Job $job */
        $job = $this->route('job');

        return [
            'status' => ['required', Rule::enum(JobStatus::class)],
            'changed_at' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$job->applied_date->toDateString(),
                'before_or_equal:'.now()->toDateString(),
            ],
            'rejection_reason' => ['nullable', Rule::enum(RejectionReason::class)],
            'offer_decision' => ['nullable', Rule::enum(OfferDecision::class)],
            'salary_offered' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        /** @var Job $job */
        $job = $this->route('job');
        $applied = $job->applied_date->locale('id')->translatedFormat('d M Y');

        return [
            'status.required' => 'Pilih status baru.',
            'status.enum' => 'Status yang dipilih tidak valid.',
            'changed_at.required' => 'Tanggal perubahan wajib diisi.',
            'changed_at.date_format' => 'Format tanggal tidak valid.',
            'changed_at.after_or_equal' => "Tanggal tidak boleh sebelum tanggal apply ({$applied}).",
            'changed_at.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'rejection_reason.enum' => 'Alasan penolakan tidak valid.',
            'offer_decision.enum' => 'Keputusan offer tidak valid.',
            'salary_offered.integer' => 'Gaji ditawarkan harus berupa angka.',
            'salary_offered.min' => 'Gaji ditawarkan tidak boleh negatif.',
            'salary_offered.max' => 'Gaji ditawarkan terlalu besar.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Cek urutan hanya kalau status dan tanggal sudah lolos validasi dasar
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Job $job */
            $job = $this->route('job');
            $status = JobStatus::from($this->input('status'));

            ['prev' => $prev, 'next' => $next] = JobStatusChanger::neighbors($job, $this->input('changed_at'));

            if ($prev?->status === $status) {
                $validator->errors()->add('status', "Loker ini sudah berstatus {$status->label()} pada tanggal tersebut.");
            } elseif ($next?->status === $status) {
                $validator->errors()->add('status', "Status {$status->label()} sudah tercatat setelah tanggal tersebut.");
            }
        });
    }
}