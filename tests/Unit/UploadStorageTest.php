<?php

namespace Tests\Unit;

use App\Support\ProfilePhoto;
use App\Support\UploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadStorageTest extends TestCase
{
    public function test_local_disks_remain_the_default_and_photos_generate_display_urls(): void
    {
        Storage::fake('public');
        config(['filesystems.public_upload_disk' => 'public']);
        config(['filesystems.private_upload_disk' => 'private']);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aWQAAAAASUVORK5CYII=');
        $stored = UploadStorage::store(UploadedFile::fake()->createWithContent('avatar.png', $png), 'photos', UploadStorage::publicDiskName());
        $url = ProfilePhoto::url($stored);

        $this->assertStringStartsWith('photos/', $stored);
        Storage::disk('public')->assertExists($stored);
        $this->assertStringEndsWith('/storage/'.$stored, (string) $url);
        $this->assertSame('public', UploadStorage::publicDiskName());
        $this->assertSame('private', UploadStorage::privateDiskName());
    }

    public function test_selected_object_storage_requires_configuration_without_exposing_values(): void
    {
        config(['filesystems.public_upload_disk' => 'r2-public']);
        config(['filesystems.disks.r2-public.endpoint' => null]);

        try {
            UploadStorage::publicDisk();
            $this->fail('Expected missing object-storage configuration to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Object storage is selected but required configuration is missing.', $exception->getMessage());
            $this->assertStringNotContainsString('secret', $exception->getMessage());
        }
    }

    public function test_private_r2_disk_has_no_configured_public_url(): void
    {
        $this->assertNull(config('filesystems.disks.r2-private.url'));
        $this->assertSame('private', config('filesystems.disks.r2-private.visibility'));
    }

    public function test_legacy_photo_key_can_resolve_from_the_configured_public_object_disk(): void
    {
        config([
            'filesystems.public_upload_disk' => 'r2-public',
            'filesystems.disks.r2-public.endpoint' => 'https://account.example.test',
            'filesystems.disks.r2-public.key' => 'test-key',
            'filesystems.disks.r2-public.secret' => 'test-secret',
            'filesystems.disks.r2-public.bucket' => 'photos',
            'filesystems.disks.r2-public.url' => 'https://images.example.test',
        ]);
        Storage::fake('r2-public');
        Storage::disk('r2-public')->put('upload/photos/legacy.jpg', 'image-bytes');

        $url = ProfilePhoto::url('upload/photos/legacy.jpg');

        $this->assertStringEndsWith('/upload/photos/legacy.jpg', (string) $url);
        $this->assertStringNotContainsString('download', strtolower((string) $url));
    }

    public function test_existing_local_photo_remains_available_during_object_storage_transition(): void
    {
        config([
            'filesystems.public_upload_disk' => 'r2-public',
            'filesystems.disks.r2-public.endpoint' => 'https://account.example.test',
            'filesystems.disks.r2-public.key' => 'test-key',
            'filesystems.disks.r2-public.secret' => 'test-secret',
            'filesystems.disks.r2-public.bucket' => 'photos',
            'filesystems.disks.r2-public.url' => 'https://images.example.test',
        ]);
        Storage::fake('public');
        Storage::fake('r2-public');
        Storage::disk('public')->put('photos/existing.jpg', 'existing-image');

        $url = ProfilePhoto::url('photos/existing.jpg');

        $this->assertStringEndsWith('/storage/photos/existing.jpg', (string) $url);
    }
}
