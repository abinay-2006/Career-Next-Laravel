<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateUploadProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName');
            $table->string('Email');
            $table->string('UserName');
            $table->string('Password')->nullable();
            $table->string('Status');
        });
        Schema::create('jobseeker_documents', function (Blueprint $table): void {
            $table->increments('DocId');
            $table->integer('JobSeekId');
            $table->string('DocumentType');
            $table->string('FileName');
            $table->string('OriginalFileName');
            $table->dateTime('UploadDate')->nullable();
            $table->string('VerificationStatus')->nullable();
            $table->text('AdminNotes')->nullable();
        });

        Storage::fake('private');
        config()->set('filesystems.private_upload_disk', 'private');
    }

    public function test_apache_allows_only_approved_image_extensions_in_photo_directories(): void
    {
        $config = file_get_contents(base_path('docker/apache-private-uploads.conf'));

        $this->assertDirectoryRule($config, '/var/www/html/public/upload', 'Require all denied');
        $this->assertDirectoryRule($config, '/var/www/html/public/uploads', 'Require all denied');

        foreach (['/var/www/html/public/upload/photos', '/var/www/html/public/uploads/photos'] as $directory) {
            $rules = $this->directoryRuleBody($config, $directory);
            $this->assertStringContainsString('Options -Indexes', $rules);
            $this->assertStringContainsString('AllowOverride None', $rules);
            $this->assertStringContainsString('Require all denied', $rules);
            $this->assertStringContainsString('<FilesMatch "(?i)\\.(?:jpe?g|png|webp)$">', $rules);
            $this->assertStringContainsString('Require all granted', $rules);
            $this->assertSame(1, preg_match('/\.(?:jpe?g|png|webp)$/i', 'avatar.jpg'));
            $this->assertSame(1, preg_match('/\.(?:jpe?g|png|webp)$/i', 'avatar.JPEG'));
            $this->assertSame(1, preg_match('/\.(?:jpe?g|png|webp)$/i', 'avatar.png'));
            $this->assertSame(1, preg_match('/\.(?:jpe?g|png|webp)$/i', 'avatar.webp'));

            foreach (['resume.pdf', 'payload.php', 'payload.phtml', 'payload.js', 'archive.zip', 'avatar.gif', 'avatar.jpg.php'] as $blockedName) {
                $this->assertSame(0, preg_match('/\.(?:jpe?g|png|webp)$/i', $blockedName), $blockedName);
            }
        }

        // The FilesMatch rules are scoped inside the photo Directory blocks;
        // Apache applies these directory rules to descendant directories too.
        $this->assertSame(2, substr_count($config, '<FilesMatch "(?i)\\.(?:jpe?g|png|webp)$">'));

        $this->assertStringContainsString('^/uploads/(470X310_1|admin)\\.jpg$', $config);
        $this->assertSame(1, preg_match('#^/uploads/(470X310_1|admin)\.jpg$#', '/uploads/470X310_1.jpg'));
        $this->assertSame(1, preg_match('#^/uploads/(470X310_1|admin)\.jpg$#', '/uploads/admin.jpg'));
        foreach (['/uploads/other.jpg', '/uploads/admin.png', '/uploads/nested/admin.jpg'] as $path) {
            $this->assertSame(0, preg_match('#^/uploads/(470X310_1|admin)\.jpg$#', $path), $path);
        }
    }

    public function test_authorized_jobseeker_route_serves_private_document_and_blocks_other_accounts(): void
    {
        $ownerId = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => 'Document Owner', 'Email' => 'owner@example.test', 'UserName' => 'owner',
            'Password' => null, 'Status' => 'Approved',
        ]);
        $otherId = DB::table('jobseeker_reg')->insertGetId([
            'JobSeekerName' => 'Other User', 'Email' => 'other@example.test', 'UserName' => 'other',
            'Password' => null, 'Status' => 'Approved',
        ]);
        $documentId = DB::table('jobseeker_documents')->insertGetId([
            'JobSeekId' => $ownerId,
            'DocumentType' => 'certificate',
            'FileName' => 'uploads/documents/test-document.pdf',
            'OriginalFileName' => 'test-document.pdf',
        ]);
        Storage::disk('private')->put('uploads/documents/test-document.pdf', '%PDF synthetic test payload');

        $owner = JobSeeker::query()->findOrFail($ownerId);
        $this->actingAs($owner, 'jobseeker')
            ->get(route('jobseeker.documents.view', $documentId))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');

        $other = JobSeeker::query()->findOrFail($otherId);
        $this->actingAs($other, 'jobseeker')
            ->get(route('jobseeker.documents.view', $documentId))
            ->assertNotFound();

        auth()->guard('jobseeker')->logout();
        $this->get(route('jobseeker.documents.view', $documentId))
            ->assertRedirect(route('login'));
    }

    private function assertDirectoryRule(string $config, string $directory, string $rule): void
    {
        $body = $this->directoryRuleBody($config, $directory);
        $this->assertStringContainsString($rule, $body);
    }

    private function directoryRuleBody(string $config, string $directory): string
    {
        $quotedDirectory = preg_quote($directory, '/');
        $this->assertSame(1, preg_match('/<Directory "'.$quotedDirectory.'">(.*?)<\/Directory>/s', $config, $matches));

        return $matches[1];
    }
}
