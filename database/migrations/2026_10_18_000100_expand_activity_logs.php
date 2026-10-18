<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table): void {
            $table->integer('JobId')->nullable()->change();
            $table->string('action', 80)->change();
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('target_type', 40)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('metadata')->nullable();
            $table->index(['actor_type', 'actor_id']);
            $table->index(['target_type', 'target_id']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS `insert`');
            DB::unprepared("CREATE TRIGGER `insert` AFTER INSERT ON `job_master` FOR EACH ROW INSERT INTO `logs` (`JobId`, `action`, `cdate`) VALUES (NEW.JobId, 'Inserted', NOW())");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS `insert`');
            DB::unprepared("CREATE TRIGGER `insert` AFTER INSERT ON `job_master` FOR EACH ROW INSERT INTO `logs` VALUES (NULL, NEW.JobId, 'Inserted', NOW())");
        }

        Schema::table('logs', function (Blueprint $table): void {
            $table->dropIndex(['actor_type', 'actor_id']);
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropColumn(['actor_type', 'actor_id', 'target_type', 'target_id', 'metadata']);
            $table->integer('JobId')->nullable(false)->change();
            $table->string('action', 20)->change();
        });
    }
};
