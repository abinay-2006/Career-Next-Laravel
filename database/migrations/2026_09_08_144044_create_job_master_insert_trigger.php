<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            CREATE TRIGGER `insert`
            AFTER INSERT ON `job_master`
            FOR EACH ROW
            INSERT INTO `logs`
            VALUES (
                NULL,
                NEW.JobId,
                'Inserted',
                NOW()
            )
        ");
    }

    public function down(): void
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS `insert`
        ");
    }
};
