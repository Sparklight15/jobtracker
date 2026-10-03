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
    Schema::create('benchmark_reference', function (Blueprint $table) {
        $table->id();
        $table->string('metric_key', 50);
        $table->string('sector', 100)->nullable();
        $table->decimal('value', 10, 2)->nullable();
        $table->enum('trend', ['growing', 'stable', 'shrinking'])->nullable();
        $table->string('source')->nullable();
        $table->year('source_year')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->index(['metric_key', 'sector']);
    });
}

public function down(): void
{
    Schema::dropIfExists('benchmark_reference');
}
};
