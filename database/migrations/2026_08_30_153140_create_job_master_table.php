<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_master', function (Blueprint $table) {
            $table->integer('JobId')->autoIncrement();

            $table->string('CompanyName', 20);
            $table->string('JobTitle', 50);
            $table->integer('Vacancy');
            $table->string('MinQualification', 50);
            $table->string('Description', 200);
            $table->string('SalaryFrom', 50)->nullable();
            $table->string('SalaryTo', 50)->nullable();
            $table->string('Status', 50)->nullable()->default('Pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_master');
    }
};
