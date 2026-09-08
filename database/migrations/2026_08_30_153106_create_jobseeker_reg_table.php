<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_reg', function (Blueprint $table) {
            $table->integer('JobSeekId')->autoIncrement();

            $table->string('JobSeekerName', 150);
            $table->string('Address', 255)->nullable();
            $table->string('City', 20);
            $table->string('Email', 150);
            $table->bigInteger('Mobile');
            $table->string('Qualification', 20);
            $table->string('Gender', 10);
            $table->date('BirthDate');
            $table->string('Resume', 255)->nullable();
            $table->string('Status', 10);
            $table->string('UserName', 100);
            $table->string('Password', 255);
            $table->string('Question', 100);
            $table->string('Answer', 50);
            $table->string('Photo', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_reg');
    }
};
