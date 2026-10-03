<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('jobs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();

        // Identitas
        $table->string('company_name', 150);
        $table->string('position', 150);
        $table->string('job_url', 500)->nullable();

        // Status terbaru (sumber kebenaran tetap status_history)
        $table->enum('current_status', ['applied', 'screening', 'interview', 'offer', 'rejected', 'ghosted']);

        // Waktu
        $table->date('applied_date');
        $table->date('first_response_date')->nullable();

        // Channel
        $table->enum('channel', ['job_portal', 'linkedin', 'referral', 'cold_apply', 'recruiter_reach_out', 'company_website', 'other']);
        $table->boolean('has_referral')->default(false);
        $table->string('referrer_name', 100)->nullable();

        // Sektor dan lokasi
        $table->string('industry_sector', 100)->nullable();
        $table->string('city', 100)->nullable();
        $table->enum('work_mode', ['remote', 'hybrid', 'onsite'])->nullable();

        // Gaji (Rupiah)
        $table->unsignedBigInteger('salary_min')->nullable();
        $table->unsignedBigInteger('salary_max')->nullable();
        $table->unsignedBigInteger('salary_offered')->nullable();

        // Fit
        $table->unsignedTinyInteger('fit_score')->nullable();
        $table->enum('cv_customization', ['generic', 'tailored'])->nullable();
        $table->unsignedTinyInteger('skill_match_score')->nullable();

        // Hasil
        $table->enum('rejection_reason', ['skill_gap', 'overqualified', 'salary_mismatch', 'no_response', 'culture_fit', 'other'])->nullable();
        $table->enum('offer_decision', ['accepted', 'declined', 'pending'])->nullable();

        $table->text('notes')->nullable();
        $table->timestamps();

        // Indeks untuk agregasi statistik
        $table->index(['user_id', 'applied_date']);
        $table->index(['user_id', 'current_status']);
    });
}

public function down(): void
{
    Schema::dropIfExists('jobs');
}
};
