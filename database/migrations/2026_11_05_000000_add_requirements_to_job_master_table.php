<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_master', function (Blueprint $table): void {
            if (!Schema::hasColumn('job_master', 'RequiredSkills')) {
                $table->json('RequiredSkills')->nullable()->after('Description');
            }
            if (!Schema::hasColumn('job_master', 'RequiredSoftware')) {
                $table->json('RequiredSoftware')->nullable()->after('RequiredSkills');
            }
        });
    }

    public function down(): void
    {
        // Requirement payloads live only in these columns; no other data depends on them.
        Schema::table('job_master', function (Blueprint $table): void {
            foreach (['RequiredSoftware', 'RequiredSkills'] as $column) {
                if (Schema::hasColumn('job_master', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
