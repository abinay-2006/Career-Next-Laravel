<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One private conversation per approved application.
 *
 * The unique index on ApplicationId is what makes "one conversation per
 * application" true even if both sides press the button in the same request: the
 * second insert is rejected and the existing row is reused. A conversation only
 * ever links the employer who owns the job to the seeker who submitted that
 * application, so employer-to-employer traffic cannot be represented at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_conversations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Signed integers, matching application_master, jobseeker_reg and employer_reg.
            $table->integer('ApplicationId')->unique('chat_conversations_application_unique');
            $table->integer('EmployerId');
            $table->integer('JobSeekId');
            // 'active' while the application is approved; 'closed' once messaging is
            // suspended or the application ends. History stays readable either way.
            $table->string('status', 16)->default('active');
            $table->dateTime('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['EmployerId', 'last_message_at'], 'chat_conversations_employer_inbox_index');
            $table->index(['JobSeekId', 'last_message_at'], 'chat_conversations_seeker_inbox_index');

            $table->foreign('ApplicationId', 'chat_conversations_application_fk')
                ->references('ApplicationId')->on('application_master')->cascadeOnDelete();
            $table->foreign('EmployerId', 'chat_conversations_employer_fk')
                ->references('EmployerId')->on('employer_reg')->cascadeOnDelete();
            $table->foreign('JobSeekId', 'chat_conversations_seeker_fk')
                ->references('JobSeekId')->on('jobseeker_reg')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_conversations');
    }
};
