<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'job_search_started_at')) {
                $table->date('job_search_started_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'apply_target')) {
                $table->integer('apply_target')->nullable();
            }
            if (!Schema::hasColumn('users', 'apply_target_period')) {
                $table->string('apply_target_period')->nullable()->default('week');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('users', 'job_search_started_at')) $columnsToDrop[] = 'job_search_started_at';
            if (Schema::hasColumn('users', 'apply_target')) $columnsToDrop[] = 'apply_target';
            if (Schema::hasColumn('users', 'apply_target_period')) $columnsToDrop[] = 'apply_target_period';

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};