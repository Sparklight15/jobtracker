<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_reference', function (Blueprint $table) {
            $table->id();
            $table->string('metric_key')->index();
            $table->string('sector')->nullable();
            $table->decimal('value', 10, 2);
            $table->string('trend')->nullable();
            $table->string('source');
            $table->integer('source_year');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_reference');
    }
};