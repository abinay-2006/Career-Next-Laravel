<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_education', function (Blueprint $table) {
            $table->integer('EduId')->autoIncrement();

            $table->integer('JobSeekId');
            $table->string('Degree', 20);
            $table->string('University', 100);
            $table->mediumInteger('PassingYear');
            $table->float('Percentage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_education');
    }
};
