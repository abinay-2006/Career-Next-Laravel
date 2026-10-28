
<?php

use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Config;

class StorageR2PrivateTest extends TestCase
{
    protected $originalPrivateUploadDisk;
    protected $originalR2PrivateDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPrivateUploadDisk = Config::get(
            'filesystems.private_upload_disk',
            'private'
        );

        $this->originalR2PrivateDisk = Config::get(
            'filesystems.disks.r2-private'
        );
    }

    protected function tearDown(): void
    {
        Config::set(
            'filesystems.private_upload_disk',
            $this->originalPrivateUploadDisk
        );

        Config::set(
            'filesystems.disks.r2-private',
            $this->originalR2PrivateDisk
        );

        parent::tearDown();
    }

    public function test_default_private_disk_is_local(): void
    {
        $this->assertSame('private', UploadStorage::privateDiskName());
        $this->assertSame(
            'local',
            Config::get('filesystems.disks.private.driver')
        );
    }

    public function test_selecting_r2_private_without_credentials_throws_exception(): void
    {
        Config::set('filesystems.private_upload_disk', 'r2-private');
        Config::set('filesystems.disks.r2-private', [
            'driver' => 's3',
            'endpoint' => 'https://example.com',
            'key' => '',
            'secret' => '',
            'bucket' => 'test-bucket',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Object storage is selected but required configuration is missing.'
        );

        UploadStorage::privateDisk();
    }

    public function test_selecting_r2_private_with_credentials_does_not_throw(): void
    {
        Config::set('filesystems.private_upload_disk', 'r2-private');
        Config::set('filesystems.disks.r2-private', [
            'driver' => 's3',
            'endpoint' => 'https://example.com',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'bucket' => 'test-bucket',
            'region' => 'auto',
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
        ]);

        $this->assertNotNull(UploadStorage::privateDisk());
    }

    public function test_r2_private_missing_secret_throws_exception(): void
    {
        Config::set('filesystems.private_upload_disk', 'r2-private');
        Config::set('filesystems.disks.r2-private', [
            'endpoint' => 'https://example.com',
            'key' => 'test-key',
            'bucket' => 'test-bucket',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Object storage is selected but required configuration is missing.'
        );

        UploadStorage::privateDisk();
    }

    public function test_r2_private_with_credentials_does_not_leak_secrets_in_exception(): void
    {
        Config::set('filesystems.private_upload_disk', 'r2-private');
        Config::set('filesystems.disks.r2-private', [
            'driver' => 's3',
            'endpoint' => 'https://example.com',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'bucket' => 'test-bucket',
            'region' => 'auto',
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
        ]);

        $this->assertNotNull(UploadStorage::privateDisk());
    }
}
