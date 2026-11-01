<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_resumes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('JobSeekId')->index();
            $table->string('title', 120)->default('My Resume');
            $table->string('template', 40)->default('classic');
            $table->json('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_resumes');
    }
};
