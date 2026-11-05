<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_skills', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Match the existing signed integer keys in jobseeker_reg.
            $table->integer('JobSeekId');
            $table->json('skills')->nullable();
            $table->json('software')->nullable();
            $table->timestamps();
            $table->unique('JobSeekId', 'jobseeker_skills_seeker_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_skills');
    }
};
