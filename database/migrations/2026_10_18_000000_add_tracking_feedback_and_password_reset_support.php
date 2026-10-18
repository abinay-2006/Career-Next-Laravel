<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_master', function (Blueprint $table): void {
            $table->dateTime('ApplicationDate')->nullable();
        });

        Schema::table('feedback', function (Blueprint $table): void {
            $table->integer('JobSeekId')->nullable()->change();
            $table->integer('EmployerId')->nullable()->after('JobSeekId');
        });

        Schema::create('password_recovery_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('account_type', 20);
            $table->unsignedBigInteger('account_id');
            $table->string('email', 150);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['account_type', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_recovery_tokens');
        Schema::table('feedback', function (Blueprint $table): void {
            $table->dropColumn('EmployerId');
            $table->integer('JobSeekId')->nullable(false)->change();
        });
        Schema::table('application_master', function (Blueprint $table): void {
            $table->dropColumn('ApplicationDate');
        });
    }
};
