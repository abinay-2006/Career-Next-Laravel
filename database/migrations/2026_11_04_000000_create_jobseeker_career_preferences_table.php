<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_career_preferences', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Match the existing signed integer keys in jobseeker_reg.
            $table->integer('JobSeekId');
            $table->json('preferred_job_titles')->nullable();
            $table->json('preferred_locations')->nullable();
            $table->decimal('minimum_salary', 12, 2)->nullable();
            $table->decimal('maximum_salary', 12, 2)->nullable();
            $table->string('salary_period', 10)->nullable();
            $table->string('currency', 3)->nullable()->default('INR');
            $table->json('employment_types')->nullable();
            $table->json('work_arrangements')->nullable();
            $table->json('preferred_industries')->nullable();
            $table->string('experience_level', 30)->nullable();
            $table->string('willing_to_relocate', 10)->nullable();
            $table->string('notice_period', 20)->nullable();
            $table->string('preferred_working_hours', 10)->nullable();
            $table->string('job_search_status', 30)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique('JobSeekId', 'jobseeker_career_preferences_seeker_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_career_preferences');
    }
};
