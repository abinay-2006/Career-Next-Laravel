<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walkin_master', function (Blueprint $table) {
            $table->integer('WalkInId')->autoIncrement();

            $table->string('CompanyName', 20);
            $table->string('JobTitle', 50);
            $table->integer('Vacancy');
            $table->string('MinQualification', 50);
            $table->string('Description', 100);
            $table->date('InterviewDate');
            $table->time('InterviewTime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walkin_master');
    }
};
