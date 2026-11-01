<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResumeTransactionSafetyTest extends TestCase
{
    private string $privateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName')->nullable();
            $table->string('Status')->default('Approved');
            $table->string('Password')->nullable();
            $table->string('Resume')->nullable();
        });

        Schema::create('jobseeker_documents', function (Blueprint $table): void {
            $table->increments('DocId');
            $table->integer('JobSeekId');
            $table->string('DocumentType');
            $table->string('FileName');
            $table->string('OriginalFileName');
            $table->dateTime('UploadDate')->nullable();
            $table->string('VerificationStatus')->nullable();
        });

        $this->privateRoot = storage_path('framework/testing/resume-safety-'.Str::uuid());
        config(['filesystems.disks.private' => [
            'driver' => 'local',
            'root' => $this->privateRoot,
            'throw' => true,
        ]]);
        Storage::forgetDisk('private');
        Storage::disk('private');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->privateRoot);
        parent::tearDown();
    }

    public function test_repeated_uploads_keep_one_resume_and_align_legacy_column(): void
    {
        $seeker = $this->createSeeker();

        $this->asSeeker($seeker)->put(route('jobseeker.profile.resume.update'), [
            'Resume' => $this->resume('first.pdf'),
        ])->assertRedirect(route('jobseeker.profile.index'));

        $firstPath = $seeker->fresh()->Resume;
        $this->asSeeker($seeker->fresh())->put(route('jobseeker.profile.resume.update'), [
            'Resume' => $this->resume('second.pdf'),
        ])->assertRedirect(route('jobseeker.profile.index'));

        $document = DB::table('jobseeker_documents')->where('JobSeekId', $seeker->JobSeekId)->first();
        $this->assertSame(1, DB::table('jobseeker_documents')->where('JobSeekId', $seeker->JobSeekId)->whereRaw('LOWER(DocumentType) = ?', ['resume'])->count());
        $this->assertSame($document->FileName, $seeker->fresh()->Resume);
        $this->assertNotSame($firstPath, $document->FileName);
        $this->assertFalse(Storage::disk('private')->exists($firstPath));
        $this->assertTrue(Storage::disk('private')->exists($document->FileName));
    }

    public function test_different_seekers_keep_separate_resume_state(): void
    {
        $first = $this->createSeeker();
        $second = $this->createSeeker();

        $this->asSeeker($first)->put(route('jobseeker.profile.resume.update'), ['Resume' => $this->resume('first.pdf')]);
        $this->asSeeker($second)->put(route('jobseeker.profile.resume.update'), ['Resume' => $this->resume('second.pdf')]);

        $this->assertSame(1, DB::table('jobseeker_documents')->where('JobSeekId', $first->JobSeekId)->whereRaw('LOWER(DocumentType) = ?', ['resume'])->count());
        $this->assertSame(1, DB::table('jobseeker_documents')->where('JobSeekId', $second->JobSeekId)->whereRaw('LOWER(DocumentType) = ?', ['resume'])->count());
        $this->assertNotSame($first->fresh()->Resume, $second->fresh()->Resume);
    }

    public function test_education_document_resume_upload_uses_the_same_single_resume_state(): void
    {
        $seeker = $this->createSeeker();

        $this->asSeeker($seeker)->post(route('jobseeker.documents.store'), [
            'cmbDocumentType' => 'resume',
            'fileDocument' => $this->resume('education-resume.pdf'),
        ])->assertRedirect(route('jobseeker.education'));

        $document = DB::table('jobseeker_documents')->where('JobSeekId', $seeker->JobSeekId)->first();
        $this->assertSame('resume', $document->DocumentType);
        $this->assertSame($document->FileName, $seeker->fresh()->Resume);
        $this->assertSame('pending', $document->VerificationStatus);

        $this->asSeeker($seeker->fresh())->delete(route('jobseeker.documents.destroy', $document->DocId))
            ->assertRedirect(route('jobseeker.education'));

        $this->assertNull($seeker->fresh()->Resume);
        $this->assertSame(0, DB::table('jobseeker_documents')->where('JobSeekId', $seeker->JobSeekId)->count());
        $this->assertFalse(Storage::disk('private')->exists($document->FileName));
    }

    public function test_failure_after_document_write_rolls_back_both_resume_records_and_cleans_new_file(): void
    {
        $seeker = $this->createSeeker();
        $documentWriteCompleted = false;

        DB::listen(function (QueryExecuted $query) use (&$documentWriteCompleted): void {
            $sql = strtolower(ltrim($query->sql));
            if (!$documentWriteCompleted && str_starts_with($sql, 'insert') && str_contains($sql, 'jobseeker_documents')) {
                $documentWriteCompleted = true;
                throw new \RuntimeException('Injected failure after document insert.');
            }
        });

        $this->asSeeker($seeker)->put(route('jobseeker.profile.resume.update'), [
            'Resume' => $this->resume('rollback.pdf'),
        ])->assertRedirect();

        $this->assertTrue($documentWriteCompleted);
        $this->assertNull($seeker->fresh()->Resume);
        $this->assertSame(0, DB::table('jobseeker_documents')->where('JobSeekId', $seeker->JobSeekId)->count());
        $this->assertSame([], Storage::disk('private')->allFiles('documents'));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_failure_after_legacy_column_write_rolls_back_document_and_column(): void
    {
        $seeker = $this->createSeeker();
        $oldPath = 'documents/existing.pdf';
        $this->addResume($seeker, $oldPath);
        Storage::disk('private')->put($oldPath, '%PDF-1.4 old');
        $legacyWriteCompleted = false;

        DB::listen(function (QueryExecuted $query) use (&$legacyWriteCompleted): void {
            $sql = strtolower(ltrim($query->sql));
            if (!$legacyWriteCompleted && str_starts_with($sql, 'update') && str_contains($sql, 'jobseeker_reg')) {
                $legacyWriteCompleted = true;
                throw new \RuntimeException('Injected failure after legacy column update.');
            }
        });

        $this->asSeeker($seeker)->put(route('jobseeker.profile.resume.update'), [
            'Resume' => $this->resume('rollback.pdf'),
        ])->assertRedirect();

        $this->assertTrue($legacyWriteCompleted);
        $this->assertSame($oldPath, $seeker->fresh()->Resume);
        $this->assertSame($oldPath, DB::table('jobseeker_documents')->where('JobSeekId', $seeker->JobSeekId)->value('FileName'));
        $this->assertTrue(Storage::disk('private')->exists($oldPath));
        $this->assertCount(1, Storage::disk('private')->allFiles('documents'));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_cancelled_account_save_rolls_back_resume_document_update(): void
    {
        $seeker = $this->createSeeker();
        $oldPath = 'documents/existing.pdf';
        $this->addResume($seeker, $oldPath);
        Storage::disk('private')->put($oldPath, '%PDF-1.4 old');
        JobSeeker::updating(function (JobSeeker $model): ?bool {
            if ($model->Resume !== 'documents/existing.pdf') {
                return false;
            }
            return null;
        });

        $this->asSeeker($seeker)->put(route('jobseeker.profile.resume.update'), [
            'Resume' => $this->resume('cancelled.pdf'),
        ])->assertRedirect();

        $this->assertSame($oldPath, $seeker->fresh()->Resume);
        $this->assertSame($oldPath, DB::table('jobseeker_documents')->where('JobSeekId', $seeker->JobSeekId)->value('FileName'));
        $this->assertTrue(Storage::disk('private')->exists($oldPath));
        $this->assertCount(1, Storage::disk('private')->allFiles('documents'));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_cancelled_document_delete_prevents_resume_removal(): void
    {
        $seeker = $this->createSeeker();
        $path = 'documents/existing.pdf';
        $this->addResume($seeker, $path);
        Storage::disk('private')->put($path, '%PDF-1.4 existing');
        JobSeekerDocument::deleting(function (JobSeekerDocument $document): ?bool {
            if ($document->FileName === 'documents/existing.pdf') {
                return false;
            }
            return null;
        });

        $this->asSeeker($seeker)->delete(route('jobseeker.profile.resume.remove'))->assertRedirect();

        $this->assertSame($path, $seeker->fresh()->Resume);
        $this->assertSame(1, DB::table('jobseeker_documents')->where('JobSeekId', $seeker->JobSeekId)->count());
        $this->assertTrue(Storage::disk('private')->exists($path));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_removal_clears_legacy_and_document_state_before_file_cleanup(): void
    {
        $seeker = $this->createSeeker();
        $path = 'documents/existing.pdf';
        $this->addResume($seeker, $path);
        Storage::disk('private')->put($path, '%PDF-1.4 existing');

        $this->asSeeker($seeker)->delete(route('jobseeker.profile.resume.remove'))
            ->assertRedirect(route('jobseeker.profile.index'));

        $this->assertNull($seeker->fresh()->Resume);
        $this->assertSame(0, DB::table('jobseeker_documents')->where('JobSeekId', $seeker->JobSeekId)->count());
        $this->assertFalse(Storage::disk('private')->exists($path));
        $this->assertSame(0, DB::transactionLevel());
    }

    private function createSeeker(): JobSeeker
    {
        $id = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Test Seeker']);
        return JobSeeker::query()->findOrFail($id);
    }

    private function addResume(JobSeeker $seeker, string $path): void
    {
        DB::table('jobseeker_reg')->where('JobSeekId', $seeker->JobSeekId)->update(['Resume' => $path]);
        DB::table('jobseeker_documents')->insert([
            'JobSeekId' => $seeker->JobSeekId,
            'DocumentType' => 'resume',
            'FileName' => $path,
            'OriginalFileName' => basename($path),
            'UploadDate' => now(),
            'VerificationStatus' => 'pending',
        ]);
    }

    private function asSeeker(JobSeeker $seeker)
    {
        return $this->actingAs($seeker, 'jobseeker');
    }

    private function resume(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 1)->mimeType('application/pdf');
    }
}
