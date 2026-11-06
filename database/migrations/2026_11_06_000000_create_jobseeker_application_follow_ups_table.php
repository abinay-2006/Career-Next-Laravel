<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidate-side follow-up activity for the Application Follow-up Coach.
 *
 * The application row and its status stay untouched - this table only holds what
 * the job seeker says they did, so the two can never be confused and an employer
 * status change can never be written from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_application_follow_ups', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Match the signed integer keys already used by application_master and jobseeker_reg.
            $table->integer('ApplicationId');
            $table->integer('JobSeekId');
            $table->string('activity_type', 32);
            $table->dateTime('activity_date');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['JobSeekId', 'ApplicationId'], 'follow_ups_seeker_application_index');
            $table->index(['ApplicationId', 'activity_date'], 'follow_ups_application_date_index');

            $table->foreign('ApplicationId', 'follow_ups_application_fk')
                ->references('ApplicationId')->on('application_master')
                ->cascadeOnDelete();
            $table->foreign('JobSeekId', 'follow_ups_seeker_fk')
                ->references('JobSeekId')->on('jobseeker_reg')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_application_follow_ups');
    }
};
