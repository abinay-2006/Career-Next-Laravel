<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['job_master', 'walkin_master'] as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'CompanyName')) {
                continue;
            }

            $column = DB::selectOne(
                'select CHARACTER_MAXIMUM_LENGTH as length from information_schema.columns where table_schema = DATABASE() and table_name = ? and column_name = ?',
                [$tableName, 'CompanyName']
            );

            if (!$column) {
                throw new RuntimeException("Could not verify the current {$tableName}.CompanyName column size.");
            }

            if ((int) $column->length < 150) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('CompanyName', 150)->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Preserve values that may now exceed the legacy 20-character width.
    }
};
