<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_jobs', function (Blueprint $table): void {
            $table->bigIncrements('SavedJobId');
            // Match the existing signed integer keys in jobseeker_reg and job_master.
            $table->integer('JobSeekId');
            $table->integer('JobId');
            $table->timestamps();
            $table->unique(['JobSeekId', 'JobId'], 'saved_jobs_seeker_job_unique');
            $table->index('JobId', 'saved_jobs_job_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_jobs');
    }
};
