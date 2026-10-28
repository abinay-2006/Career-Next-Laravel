<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use App\Models\Employer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateDocumentAccessTest extends TestCase
{
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
        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId');
            $table->string('CompanyName');
            $table->string('Status')->default('Approved');
            $table->string('Password')->nullable();
        });
        Schema::create('job_master', function (Blueprint $table): void {
            $table->increments('JobId');
            $table->integer('EmployerId')->nullable();
            $table->string('CompanyName');
        });
        Schema::create('application_master', function (Blueprint $table): void {
            $table->increments('ApplicationId');
            $table->integer('JobSeekId');
            $table->integer('JobId');
        });
        Storage::fake('private');
    }

    public function test_document_download_requires_authentication_and_only_allows_its_owner(): void
    {
        $ownerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Owner']);
        $otherId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Other']);
        Storage::disk('private')->put('documents/owner.pdf', '%PDF-1.4 private');
        $documentId = DB::table('jobseeker_documents')->insertGetId([
            'JobSeekId' => $ownerId, 'DocumentType' => 'resume', 'FileName' => 'documents/owner.pdf',
            'OriginalFileName' => 'owner.pdf', 'UploadDate' => now(), 'VerificationStatus' => 'pending',
        ]);

        $this->get(route('jobseeker.documents.view', $documentId))->assertRedirect(route('login'));
        $this->get('/storage/documents/owner.pdf')->assertNotFound();

        $this->actingAs(JobSeeker::query()->findOrFail($otherId), 'jobseeker')
            ->get(route('jobseeker.documents.view', $documentId))->assertNotFound();

        $this->actingAs(JobSeeker::query()->findOrFail($ownerId), 'jobseeker')
            ->get(route('jobseeker.documents.view', $documentId))
            ->assertOk()->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('content-disposition', 'inline; filename=owner.pdf');
    }

    public function test_missing_private_document_returns_404_and_invalid_upload_type_is_rejected(): void
    {
        $ownerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Owner']);
        $documentId = DB::table('jobseeker_documents')->insertGetId([
            'JobSeekId' => $ownerId, 'DocumentType' => 'certificate', 'FileName' => 'documents/missing.pdf',
            'OriginalFileName' => 'missing.pdf', 'UploadDate' => now(), 'VerificationStatus' => 'pending',
        ]);

        $this->actingAs(JobSeeker::query()->findOrFail($ownerId), 'jobseeker')
            ->get(route('jobseeker.documents.view', $documentId))->assertNotFound();

        $this->post(route('jobseeker.documents.store'), [
            'cmbDocumentType' => 'certificate',
            'fileDocument' => UploadedFile::fake()->createWithContent('payload.exe', 'MZ executable payload'),
        ])->assertSessionHasErrors('fileDocument');
    }

    public function test_all_supported_education_document_formats_are_accepted_and_served_privately(): void
    {
        $ownerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Owner']);
        $owner = JobSeeker::query()->findOrFail($ownerId);

        $uploads = [
            UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\nprivate test document"),
            UploadedFile::fake()->createWithContent('proof.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9"),
            UploadedFile::fake()->createWithContent('proof.jpeg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9"),
            UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aWQAAAAASUVORK5CYII=')),
        ];

        foreach ($uploads as $upload) {
            $this->actingAs($owner, 'jobseeker')
                ->post(route('jobseeker.documents.store'), [
                    'cmbDocumentType' => 'certificate',
                    'fileDocument' => $upload,
                ])
                ->assertSessionHasNoErrors();
        }

        $documents = DB::table('jobseeker_documents')->where('JobSeekId', $ownerId)->get();
        $this->assertCount(4, $documents);

        foreach ($documents as $document) {
            $this->actingAs($owner, 'jobseeker')
                ->get(route('jobseeker.documents.view', $document->DocId))
                ->assertOk()
                ->assertHeader('cache-control', 'no-store, private')
                ->assertHeader('x-content-type-options', 'nosniff');
        }
    }

    public function test_all_supported_resume_formats_are_stored_on_the_private_disk(): void
    {
        $ownerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Owner']);
        $owner = JobSeeker::query()->findOrFail($ownerId);
        $uploads = [
            UploadedFile::fake()->create('resume.pdf', 1)->mimeType('application/pdf'),
            UploadedFile::fake()->create('resume.doc', 1)->mimeType('application/msword'),
            UploadedFile::fake()->create('resume.docx', 1)->mimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];

        foreach ($uploads as $upload) {
            $this->actingAs($owner, 'jobseeker')
                ->put(route('jobseeker.profile.resume.update'), ['Resume' => $upload])
                ->assertRedirect(route('jobseeker.profile.index'))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(1, DB::table('jobseeker_documents')->where('JobSeekId', $ownerId)->where('DocumentType', 'resume')->count());
        $resumePath = DB::table('jobseeker_documents')->where('JobSeekId', $ownerId)->value('FileName');
        $this->assertStringStartsWith('documents/resume_' . $ownerId . '_', $resumePath);
        Storage::disk('private')->assertExists($resumePath);
    }

    public function test_legacy_document_remains_available_only_through_an_authorized_route(): void
    {
        $ownerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Owner']);
        $otherId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Other']);
        $legacyDirectory = public_path('uploads/documents');
        File::ensureDirectoryExists($legacyDirectory);
        $legacyName = 'private-upload-test-' . bin2hex(random_bytes(6)) . '.pdf';
        $legacyPath = $legacyDirectory . DIRECTORY_SEPARATOR . $legacyName;

        File::put($legacyPath, "%PDF-1.4\nlegacy test document");
        $documentId = DB::table('jobseeker_documents')->insertGetId([
            'JobSeekId' => $ownerId,
            'DocumentType' => 'certificate',
            'FileName' => $legacyName,
            'OriginalFileName' => 'legacy.pdf',
            'UploadDate' => now(),
            'VerificationStatus' => 'pending',
        ]);

        try {
            $this->get(route('jobseeker.documents.view', $documentId))
                ->assertRedirect(route('login'));
            $this->get('/uploads/documents/'.$legacyName)->assertNotFound();

            $this->actingAs(JobSeeker::query()->findOrFail($otherId), 'jobseeker')
                ->get(route('jobseeker.documents.view', $documentId))
                ->assertNotFound();

            $this->actingAs(JobSeeker::query()->findOrFail($ownerId), 'jobseeker')
                ->get(route('jobseeker.documents.view', $documentId))
                ->assertOk()
                ->assertHeader('cache-control', 'no-store, private')
                ->assertHeader('x-content-type-options', 'nosniff');
        } finally {
            File::delete($legacyPath);
        }
    }

    public function test_staged_private_legacy_key_is_served_through_the_authorized_route_only(): void
    {
        $ownerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Owner']);
        $otherId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Other']);
        $legacyName = 'staged-private-'.bin2hex(random_bytes(5)).'.pdf';
        Storage::disk('private')->put('uploads/documents/'.$legacyName, "%PDF-1.4\nprivate legacy");
        $documentId = DB::table('jobseeker_documents')->insertGetId([
            'JobSeekId' => $ownerId,
            'DocumentType' => 'certificate',
            'FileName' => $legacyName,
            'OriginalFileName' => 'private.pdf',
            'UploadDate' => now(),
            'VerificationStatus' => 'pending',
        ]);

        $this->get('/uploads/documents/'.$legacyName)->assertNotFound();
        $this->actingAs(JobSeeker::query()->findOrFail($otherId), 'jobseeker')
            ->get(route('jobseeker.documents.view', $documentId))->assertNotFound();

        $this->actingAs(JobSeeker::query()->findOrFail($ownerId), 'jobseeker')
            ->get(route('jobseeker.documents.view', $documentId))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=private.pdf')
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('x-content-type-options', 'nosniff');
    }

    public function test_private_local_file_remains_available_during_object_storage_transition(): void
    {
        $ownerId = DB::table('jobseeker_reg')->insertGetId(['JobSeekerName' => 'Owner']);
        Storage::disk('private')->put('documents/pre-transition.pdf', "%PDF-1.4\nlocal private file");
        $documentId = DB::table('jobseeker_documents')->insertGetId([
            'JobSeekId' => $ownerId,
            'DocumentType' => 'resume',
            'FileName' => 'documents/pre-transition.pdf',
            'OriginalFileName' => 'resume.pdf',
            'UploadDate' => now(),
            'VerificationStatus' => 'pending',
        ]);

        config([
            'filesystems.private_upload_disk' => 'r2-private',
            'filesystems.disks.r2-private.endpoint' => 'https://account.example.test',
            'filesystems.disks.r2-private.key' => 'test-key',
            'filesystems.disks.r2-private.secret' => 'test-secret',
            'filesystems.disks.r2-private.bucket' => 'private-documents',
        ]);
        Storage::fake('r2-private');

        $this->actingAs(JobSeeker::query()->findOrFail($ownerId), 'jobseeker')
            ->get(route('jobseeker.documents.view', $documentId))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=resume.pdf')
            ->assertHeader('cache-control', 'no-store, private');
    }

    public function test_employer_can_view_owned_legacy_resume_but_another_employer_cannot(): void
    {
        $ownerEmployerId = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Same Company']);
        $otherEmployerId = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Same Company']);
        $jobId = DB::table('job_master')->insertGetId([
            'EmployerId' => $ownerEmployerId,
            'CompanyName' => 'Same Company',
        ]);
        $seekerId = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => 'Applicant',
            'Resume' => 'legacy-employer-resume.pdf',
        ]);
        $applicationId = DB::table('application_master')->insertGetId([
            'JobSeekId' => $seekerId,
            'JobId' => $jobId,
        ]);
        $legacyDirectory = public_path('uploads/documents');
        File::ensureDirectoryExists($legacyDirectory);
        $legacyPath = $legacyDirectory . DIRECTORY_SEPARATOR . 'legacy-employer-resume.pdf';

        File::put($legacyPath, "%PDF-1.4\nlegacy resume test");

        try {
            $this->get(route('employer.applications.resume', $applicationId))
                ->assertRedirect(route('login'));

            $this->actingAs(Employer::query()->findOrFail($otherEmployerId), 'employer')
                ->get(route('employer.applications.resume', $applicationId))
                ->assertForbidden();

            $this->actingAs(Employer::query()->findOrFail($ownerEmployerId), 'employer')
                ->get(route('employer.applications.resume', $applicationId))
                ->assertOk()
                ->assertHeader('cache-control', 'no-store, private')
                ->assertHeader('x-content-type-options', 'nosniff');
        } finally {
            File::delete($legacyPath);
        }
    }

    public function test_employer_missing_resume_returns_404(): void
    {
        $employerId = DB::table('employer_reg')->insertGetId(['CompanyName' => 'Employer']);
        $jobId = DB::table('job_master')->insertGetId([
            'EmployerId' => $employerId,
            'CompanyName' => 'Employer',
        ]);
        $seekerId = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => 'Applicant',
            'Resume' => 'missing-employer-resume.pdf',
        ]);
        $applicationId = DB::table('application_master')->insertGetId([
            'JobSeekId' => $seekerId,
            'JobId' => $jobId,
        ]);

        $this->actingAs(Employer::query()->findOrFail($employerId), 'employer')
            ->get(route('employer.applications.resume', $applicationId))
            ->assertNotFound();
    }

    public function test_apache_policy_text_declares_denied_and_public_locations(): void
    {
        $configuration = file_get_contents(base_path('docker/apache-private-uploads.conf'));

        $this->assertIsString($configuration);
        $this->assertStringContainsString('<Directory "/var/www/html/public/upload">', $configuration);
        $this->assertStringContainsString('<Directory "/var/www/html/public/uploads">', $configuration);
        $this->assertGreaterThanOrEqual(2, substr_count($configuration, 'Require all denied'));
        $this->assertStringContainsString('<Directory "/var/www/html/public/upload/photos">', $configuration);
        $this->assertStringContainsString('<Directory "/var/www/html/public/uploads/photos">', $configuration);
        $this->assertStringContainsString('<LocationMatch "^/uploads/(470X310_1|admin)\\.jpg$">', $configuration);
        $this->assertStringNotContainsString('<Files ', $configuration);
    }
}
