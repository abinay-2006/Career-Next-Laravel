<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->integer('FeedbackId')->autoIncrement();

            $table->integer('JobSeekId');
            $table->string('Feedback', 200);
            $table->date('FeedbakDate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
