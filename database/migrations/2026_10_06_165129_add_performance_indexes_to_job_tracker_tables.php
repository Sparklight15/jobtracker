<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            // Mempercepat filter cohort di StatsContext
            $table->index(['user_id', 'applied_date'], 'idx_jobs_user_applied');
            // Mempercepat perhitungan Grup I (skor prediksi)
            $table->index('current_status', 'idx_jobs_current_status');
        });

        Schema::table('status_history', function (Blueprint $table) {
            // Mempercepat eager loading dan sorting status loker
            $table->index(['job_id', 'changed_at'], 'idx_status_job_changed');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('idx_jobs_user_applied');
            $table->dropIndex('idx_jobs_current_status');
        });

        Schema::table('status_history', function (Blueprint $table) {
            $table->dropIndex('idx_status_job_changed');
        });
    }
};