<?php

namespace App\Http\Requests;

use App\Enums\ApplyTargetPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Email sengaja tidak divalidasi: field-nya disabled di form dan tidak boleh diubah.
     * Karena controller memakai validated(), email dari request manual ikut diabaikan.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            // Kolom users.name: string(100), nullable (register hanya meminta email)
            'name' => ['nullable', 'string', 'max:100'],
            'job_search_started_at' => ['nullable', 'date', 'before_or_equal:today'],
            // Target dan periode harus diisi berpasangan
            'apply_target' => ['nullable', 'integer', 'min:1', 'max:1000', 'required_with:apply_target_period'],
            'apply_target_period' => ['nullable', Rule::enum(ApplyTargetPeriod::class), 'required_with:apply_target'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Nama maksimal :max karakter.',
            'job_search_started_at.date' => 'Tanggal tidak valid.',
            'job_search_started_at.before_or_equal' => 'Tanggal mulai tidak boleh di masa depan.',
            'apply_target.integer' => 'Target harus berupa angka bulat.',
            'apply_target.min' => 'Target minimal :min.',
            'apply_target.max' => 'Target maksimal :max.',
            'apply_target.required_with' => 'Isi jumlah target karena periode sudah dipilih.',
            'apply_target_period.enum' => 'Periode tidak valid.',
            'apply_target_period.required_with' => 'Pilih periode karena target sudah diisi.',
        ];
    }
}