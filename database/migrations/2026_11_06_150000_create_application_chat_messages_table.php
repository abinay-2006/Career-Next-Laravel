<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The messages inside one application conversation.
 *
 * SenderId/SenderRole are written by the server from the authenticated guard only,
 * never from the request, so a participant cannot speak as the other side. read_at
 * is set by the recipient alone and is the source for both the unread badge and the
 * sender's read receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A failed attempt to add the foreign key can leave this table behind
        // without recording the migration. Recreate that partial table cleanly.
        if (Schema::hasTable('application_chat_messages')) {
            Schema::drop('application_chat_messages');
        }

        Schema::create('application_chat_messages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Match application_conversations.id (unsigned BIGINT) for the FK.
            $table->unsignedBigInteger('ConversationId');
            // Which participant wrote this row; 'employer' or 'jobseeker'.
            $table->integer('SenderId');
            $table->string('SenderRole', 16);
            // Plain text only. Tags are stripped on the way in and Blade escapes on the
            // way out, so a message can never carry markup into another user's page.
            $table->text('message');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['ConversationId', 'id'], 'chat_messages_conversation_index');
            $table->index(['ConversationId', 'read_at'], 'chat_messages_conversation_unread_index');
            $table->index(['ConversationId', 'SenderRole', 'read_at'], 'chat_messages_conversation_receipt_index');

            $table->foreign('ConversationId', 'chat_messages_conversation_fk')
                ->references('id')->on('application_conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_chat_messages');
    }
};
