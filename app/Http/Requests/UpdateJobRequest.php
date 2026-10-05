<?php

namespace App\Http\Requests;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\StatusHistory;
use App\Support\JobStatusChanger;
use App\Support\JobUpdater;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class UpdateJobRequest extends JobFormRequest
{
    public function authorize(): bool
    {
        // Bukan pemilik: JobPolicy menghasilkan 404, dicek sebelum validasi apa pun
        Gate::authorize('update', $this->route('job'));

        return true;
    }

    /**
     * Di form Edit, status dianggap berubah hanya kalau berbeda dari status saat ini.
     * Kalau sama, tanggal perubahan tidak diminta dan tidak ada baris riwayat baru.
     */
    protected function wantsStatusChange(): bool
    {
        $status = $this->selectedStatus();

        return $status !== null && $status !== $this->route('job')->current_status;
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator) {
            // Cek yang bergantung pada riwayat hanya kalau validasi dasar sudah lolos
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Job $job */
            $job = $this->route('job');

            $this->checkAppliedDate($validator, $job);
            $this->checkStatusOrder($validator, $job);
        });
    }

    /**
     * Tanggal apply baru tidak boleh melewati riwayat lain yang paling awal,
     * karena riwayat awal "Applied" ikut dipindahkan ke tanggal apply.
     */
    private function checkAppliedDate(Validator $validator, Job $job): void
    {
        $initial = JobUpdater::initialAppliedEntry($job);

        $earliest = StatusHistory::query()
            ->where('job_id', $job->id)
            ->when($initial, fn ($q) => $q->where('id', '!=', $initial->id))
            ->min('changed_at');

        if ($earliest === null) {
            return;
        }

        $earliest = Carbon::parse($earliest);

        if ($this->input('applied_date') > $earliest->toDateString()) {
            $date = $earliest->locale('id')->translatedFormat('d M Y');

            $validator->errors()->add(
                'applied_date',
                "Tanggal apply tidak boleh setelah riwayat status paling awal ({$date})."
            );
        }
    }

    /**
     * Aturan urutan yang sama dengan fitur Ubah Status (UpdateJobStatusRequest).
     */
    private function checkStatusOrder(Validator $validator, Job $job): void
    {
        if (! $this->wantsStatusChange()) {
            return;
        }

        $status = JobStatus::from($this->input('status'));

        ['prev' => $prev, 'next' => $next] = JobStatusChanger::neighbors($job, $this->input('changed_at'));

        if ($prev?->status === $status) {
            $validator->errors()->add('status', "Loker ini sudah berstatus {$status->label()} pada tanggal tersebut.");
        } elseif ($next?->status === $status) {
            $validator->errors()->add('status', "Status {$status->label()} sudah tercatat setelah tanggal tersebut.");
        }
    }
}