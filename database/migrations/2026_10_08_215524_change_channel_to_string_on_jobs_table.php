<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // enum -> string: data lama tetap utuh, ke depan cukup edit enum PHP.
        // Raw SQL dipakai supaya tidak perlu doctrine/dbal.
        DB::statement('ALTER TABLE jobs MODIFY channel VARCHAR(50) NOT NULL');
    }

    public function down(): void
    {
        // Nilai baru tidak ada di enum lama, jadi dipetakan ke 'other' dulu
        DB::table('jobs')
            ->whereNotIn('channel', [
                'job_portal', 'linkedin', 'referral', 'cold_apply',
                'recruiter_reach_out', 'company_website', 'other',
            ])
            ->update(['channel' => 'other']);

        DB::statement(
            "ALTER TABLE jobs MODIFY channel ENUM('job_portal','linkedin','referral','cold_apply','recruiter_reach_out','company_website','other') NOT NULL"
        );
    }
};