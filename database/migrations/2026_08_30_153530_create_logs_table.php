<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {

            $table->engine = 'MyISAM';
            $table->charset = 'latin1';
            $table->collation = 'latin1_swedish_ci';

            $table->integer('Id')->autoIncrement();
            $table->integer('JobId');
            $table->string('action', 20);
            $table->dateTime('cdate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
