<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            $requiredColumns = ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity'];
            $missingColumns = array_values(array_filter(
                $requiredColumns,
                fn (string $column): bool => !Schema::hasColumn('sessions', $column)
            ));

            if ($missingColumns !== []) {
                throw new RuntimeException(
                    'The existing sessions table is incomplete. Missing columns: ' . implode(', ', $missingColumns)
                );
            }

            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        // Preserve persisted sessions during code rollback. The table can be
        // removed manually only after an explicit operational review.
    }
};
