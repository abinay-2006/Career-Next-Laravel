<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_reg', function (Blueprint $table) {
            $table->integer('EmployerId')->autoIncrement();

            $table->string('CompanyName', 150);
            $table->string('ContactPerson', 255)->nullable();
            $table->text('Address')->nullable();    
            $table->string('City', 100)->nullable();
            $table->string('Email', 150);
            $table->string('Mobile', 20)->nullable();
            $table->string('Area_Work', 255)->nullable();
            $table->string('Status', 10);
            $table->string('UserName', 100);
            $table->string('Password', 255);
            $table->string('Question', 100)->nullable();
            $table->string('Answer', 50)->nullable();
            $table->string('Photo', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_reg');
    }
};
