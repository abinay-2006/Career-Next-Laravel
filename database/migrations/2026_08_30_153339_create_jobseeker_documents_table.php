<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobseeker_documents', function (Blueprint $table) {
            $table->integer('DocId')->autoIncrement();

            $table->integer('JobSeekId');

            $table->string('DocumentType', 50);
            $table->string('FileName', 255);
            $table->string('OriginalFileName', 255);

            $table->dateTime('UploadDate')->useCurrent();

            $table->enum('VerificationStatus', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');

            $table->text('AdminNotes')->nullable();

            $table->foreign('JobSeekId')
                ->references('JobSeekId')
                ->on('jobseeker_reg')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobseeker_documents');
    }
};
