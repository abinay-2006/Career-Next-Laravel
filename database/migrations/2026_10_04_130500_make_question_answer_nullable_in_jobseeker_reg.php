<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jobseeker_reg', function (Blueprint $table) {
            $table->string('Question')->nullable()->change();
            $table->string('Answer')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('jobseeker_reg', function (Blueprint $table) {
            $table->string('Question')->nullable(false)->change();
            $table->string('Answer')->nullable(false)->change();
        });
    }
};
