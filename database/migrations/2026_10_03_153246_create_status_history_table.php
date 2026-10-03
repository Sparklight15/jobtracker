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
    Schema::create('status_history', function (Blueprint $table) {
        $table->id();
        $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
        $table->enum('status', ['applied', 'screening', 'interview', 'offer', 'rejected', 'ghosted']);
        $table->date('changed_at');
        $table->timestamp('created_at')->useCurrent();

        // Untuk waktu per tahap (#7) dan tren funnel (#29)
        $table->index(['job_id', 'changed_at']);
    });
}

public function down(): void
{
    Schema::dropIfExists('status_history');
}
};
