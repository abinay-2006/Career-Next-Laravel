<?php

namespace Tests\Unit;

use App\Services\StorageMigrationService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageMigrationServiceTest extends TestCase
{
    private string $root;
    private StorageMigrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('app/migration-input/test-'.bin2hex(random_bytes(5)));
        File::ensureDirectoryExists($this->root);
        File::put($this->root.DIRECTORY_SEPARATOR.'sample.pdf', "%PDF-1.4\nexample");
        config(['filesystems.public_upload_disk' => 'migration-public']);
        config(['filesystems.private_upload_disk' => 'migration-private']);
        Storage::fake('migration-public');
        Storage::fake('migration-private');
        $this->service = new StorageMigrationService();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_inventory_is_read_only_and_contains_metadata_only(): void
    {
        $rows = $this->service->inventory($this->root, 'private-document');

        $this->assertCount(1, $rows);
        $this->assertSame('sample.pdf', $rows[0]['source']);
        $this->assertSame('private-document', $rows[0]['category']);
        $this->assertSame(hash_file('sha256', $this->root.DIRECTORY_SEPARATOR.'sample.pdf'), $rows[0]['sha256']);
        $this->assertSame('not_checked', $rows[0]['reference_status']);
        $this->assertSame('not_checked', $rows[0]['destination_status']);
        Storage::disk('migration-private')->assertMissing('uploads/documents/sample.pdf');
        $this->assertFileExists($this->root.DIRECTORY_SEPARATOR.'sample.pdf');
    }

    public function test_migration_dry_run_and_copy_retry_never_overwrite(): void
    {
        $dryRun = $this->service->migrate($this->root, 'private-document', 'uploads/documents', false);
        $this->assertSame('skipped', $dryRun[0]['destination_status']);
        Storage::disk('migration-private')->assertMissing('uploads/documents/sample.pdf');

        $copied = $this->service->migrate($this->root, 'private-document', 'uploads/documents', true);
        $this->assertSame('copied', $copied[0]['destination_status']);
        Storage::disk('migration-private')->assertExists('uploads/documents/sample.pdf');

        $retry = $this->service->migrate($this->root, 'private-document', 'uploads/documents', true);
        $this->assertSame('already_verified', $retry[0]['destination_status']);

        Storage::disk('migration-private')->put('uploads/documents/sample.pdf', 'different object');
        $collision = $this->service->migrate($this->root, 'private-document', 'uploads/documents', true);
        $this->assertSame('skipped', $collision[0]['destination_status']);
        $this->assertSame('different object', Storage::disk('migration-private')->get('uploads/documents/sample.pdf'));
    }

    public function test_verify_only_reports_missing_destination_and_rejects_unapproved_roots(): void
    {
        $result = $this->service->migrate($this->root, 'public-photo', 'upload/photos', false, true);
        $this->assertSame('verification_failed', $result[0]['destination_status']);

        $this->expectException(\RuntimeException::class);
        $this->service->inventory(base_path(), 'public-photo');
    }
}
