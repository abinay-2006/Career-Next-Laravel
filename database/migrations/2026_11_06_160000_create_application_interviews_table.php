<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interview invitations raised on an accepted application.
 *
 * One row is one round, and a round is revised in place: the same row moves
 * proposed -> reschedule_requested -> proposed -> confirmed as the two people
 * answer each other, so the interview the seeker confirmed is never a different
 * record from the one that was first offered. reschedule_requested_at,
 * reschedule_reason, reschedule_preferred_at and revision_count keep what was
 * asked for and how often it changed, and ActivityLogger holds the full trail.
 *
 * An invitation is "open" while it still expects an answer. active_marker is 1
 * for those rows and null once the interview is declined, cancelled or completed,
 * and the unique index over (ApplicationId, round, active_marker) is what makes
 * "one open invitation per round" true even when both buttons are pressed in the
 * same request - a second insert is rejected and the caller reads back the row
 * that won. Different rounds are different tuples, so a second or third round is
 * allowed while an earlier one is still open.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A failed attempt to add the foreign keys can leave this table behind
        // without recording the migration. Recreate that partial table cleanly.
        if (Schema::hasTable('application_interviews')) {
            Schema::drop('application_interviews');
        }

        Schema::create('application_interviews', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Signed integers, matching application_master and employer_reg.
            $table->integer('ApplicationId');
            // Which interview this is for the application: 1, 2, 3...
            $table->unsignedSmallInteger('round')->default(1);
            // Stored as written; timezone records what it was written in.
            $table->dateTime('scheduled_at');
            $table->string('timezone', 64);
            $table->string('interview_type', 16);
            // Filled by type: online needs meeting_link, in_person needs location.
            $table->string('meeting_link', 500)->nullable();
            $table->string('location', 255)->nullable();
            // Plain text. Tags are stripped on the way in and Blade escapes on the
            // way out, so instructions cannot carry markup into another user's page.
            $table->text('instructions')->nullable();
            $table->string('status', 24)->default('proposed');
            // 1 while the invitation still expects an answer, null once it is over.
            $table->unsignedTinyInteger('active_marker')->nullable()->default(null);

            // The seeker's answer: when they replied and what they asked for instead.
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('reschedule_requested_at')->nullable();
            $table->string('reschedule_reason', 500)->nullable();
            $table->dateTime('reschedule_preferred_at')->nullable();

            // The employer's revisions of this same round.
            $table->unsignedTinyInteger('revision_count')->default(0);
            $table->dateTime('revised_at')->nullable();
            // When the row stopped expecting an answer: declined, cancelled or completed.
            $table->dateTime('concluded_at')->nullable();

            $table->integer('created_by_employer_id');
            $table->timestamps();

            $table->unique(
                ['ApplicationId', 'round', 'active_marker'],
                'interviews_round_open_unique'
            );
            $table->index(['ApplicationId', 'round'], 'interviews_application_round_index');
            $table->index(['created_by_employer_id', 'status'], 'interviews_employer_status_index');
            $table->index(['status', 'scheduled_at'], 'interviews_status_schedule_index');

            $table->foreign('ApplicationId', 'interviews_application_fk')
                ->references('ApplicationId')->on('application_master')->cascadeOnDelete();
            $table->foreign('created_by_employer_id', 'interviews_creator_fk')
                ->references('EmployerId')->on('employer_reg')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_interviews');
    }
};
