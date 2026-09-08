<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_master', function (Blueprint $table) {
            $table->integer('UserId')->autoIncrement();
            $table->string('UserName', 20);
            $table->string('Password', 255);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_master');
    }
};
