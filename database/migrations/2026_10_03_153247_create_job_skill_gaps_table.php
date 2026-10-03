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
    Schema::create('job_skill_gaps', function (Blueprint $table) {
        $table->id();
        $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
        $table->string('skill_name', 100);

        // Satu skill hanya boleh tercatat sekali per loker
        $table->unique(['job_id', 'skill_name']);
    });
}

public function down(): void
{
    Schema::dropIfExists('job_skill_gaps');
}
};
