<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('job_master', 'EmployerId')) {
            Schema::table('job_master', function (Blueprint $table): void {
                // Nullable so legacy jobs remain intact until their ownership
                // can be established from reliable records.
                $table->unsignedInteger('EmployerId')->nullable()->index();
            });
        }
        if (Schema::hasTable('walkin_master') && !Schema::hasColumn('walkin_master', 'EmployerId')) {
            Schema::table('walkin_master', function (Blueprint $table): void {
                $table->unsignedInteger('EmployerId')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Deliberately retain the ownership data during rollback. Removing
        // this column requires an explicit data-retention review.
    }
};
