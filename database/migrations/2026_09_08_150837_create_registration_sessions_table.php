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
        Schema::create('registration_sessions', function (Blueprint $table) {

            $table->id();

            // JobSeeker or Employer
            $table->string('account_type', 20);

            // Basic registration information
            $table->string('name');
            $table->string('username', 100);
            $table->string('email');
            $table->string('mobile', 30)->nullable();

            // Hashed password
            $table->string('password');

            // Additional registration data
            $table->json('registration_data')->nullable();

            // Email verification
            $table->string('otp', 6)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();

            // OTP/security tracking
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('otp_last_sent_at')->nullable();

            $table->timestamps();

            // Automatically remove abandoned registrations
            $table->index('email');
            $table->index('username');
            $table->index('account_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_sessions');
    }
};
