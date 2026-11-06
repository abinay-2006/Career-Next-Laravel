<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up messages the job seeker chose to deliver to an employer from the
 * Application Follow-up Coach.
 *
 * This table is deliberately separate from jobseeker_application_follow_ups: the
 * tracker holds private notes the seeker recorded for themselves, and only rows in
 * here have been handed to the employer on purpose. An employer query can therefore
 * never reach a private note, even by accident.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_follow_up_messages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Signed integers, matching application_master, jobseeker_reg and employer_reg.
            $table->integer('ApplicationId');
            $table->integer('JobSeekId');
            $table->integer('EmployerId');
            $table->string('template', 32)->nullable();
            $table->string('subject', 200);
            $table->text('body');
            $table->dateTime('sent_at');
            // Only ever set by the employer, and never overwritten once read.
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['EmployerId', 'read_at', 'sent_at'], 'fu_messages_employer_inbox_index');
            $table->index(['ApplicationId', 'sent_at'], 'fu_messages_application_index');
            $table->index(['JobSeekId', 'sent_at'], 'fu_messages_seeker_index');

            $table->foreign('ApplicationId', 'fu_messages_application_fk')
                ->references('ApplicationId')->on('application_master')
                ->cascadeOnDelete();
            $table->foreign('JobSeekId', 'fu_messages_seeker_fk')
                ->references('JobSeekId')->on('jobseeker_reg')
                ->cascadeOnDelete();
            $table->foreign('EmployerId', 'fu_messages_employer_fk')
                ->references('EmployerId')->on('employer_reg')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_follow_up_messages');
    }
};
