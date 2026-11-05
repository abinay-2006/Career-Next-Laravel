<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'ExperienceLevel' => 30,
        'WorkArrangement' => 30,
        'EmploymentType' => 50,
    ];

    public function up(): void
    {
        Schema::table('job_master', function (Blueprint $table): void {
            $after = 'MinQualification';

            foreach (self::COLUMNS as $column => $length) {
                if (!Schema::hasColumn('job_master', $column)) {
                    $table->string($column, $length)->nullable()->after($after);
                }

                $after = $column;
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_master', function (Blueprint $table): void {
            foreach (array_reverse(array_keys(self::COLUMNS)) as $column) {
                if (Schema::hasColumn('job_master', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
