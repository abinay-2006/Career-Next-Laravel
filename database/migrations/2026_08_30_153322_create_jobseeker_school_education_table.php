<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_school_education', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();

            $table->integer('JobSeekId');

            $table->string('SchoolName', 255)->nullable();
            $table->string('Board', 100)->nullable();
            $table->year('YearOfPassing')->nullable();
            $table->decimal('Percentage', 5, 2)->nullable();
            $table->string('Qualification', 100)->nullable();

            $table->foreign('JobSeekId')
                ->references('JobSeekId')
                ->on('jobseeker_reg');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_school_education');
    }
};
