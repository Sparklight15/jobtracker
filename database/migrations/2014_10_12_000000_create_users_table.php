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
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name', 100)->nullable();
        $table->string('email')->unique();
        $table->string('password');
        $table->date('job_search_started_at')->nullable();
        $table->unsignedSmallInteger('apply_target')->nullable();
        $table->enum('apply_target_period', ['week', 'month'])->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
