<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Selects upload disks and verifies writes without changing stored key formats. */
class UploadStorage
{
    public static function publicDiskName(): string
    {
        $diskName = (string) config('filesystems.public_upload_disk', 'public');
        self::validateDiskName('public_upload_disk', $diskName);
        return $diskName;
    }

    public static function privateDiskName(): string
    {
        $diskName = (string) config('filesystems.private_upload_disk', 'private');
        self::validateDiskName('private_upload_disk', $diskName);
        return $diskName;
    }

    public static function publicDisk()
    {
        return self::disk(self::publicDiskName());
    }

    public static function privateDisk()
    {
        return self::disk(self::privateDiskName());
    }

    public static function store(UploadedFile $file, string $directory, string $diskName): string
    {
        self::assertConfigured($diskName);

        $path = $file->store($directory, $diskName);
        return self::confirmStored($path, $diskName);
    }

    public static function storeAs(UploadedFile $file, string $directory, string $name, string $diskName): string
    {
        self::assertConfigured($diskName);

        $path = $file->storeAs($directory, $name, $diskName);
        return self::confirmStored($path, $diskName);
    }

    public static function displayUrl(string $path): string
    {
        $diskName = self::publicDiskName();
        self::assertConfigured($diskName);

        if ($diskName === 'public') {
            return url('/storage/' . $path);
        }

        return Storage::disk($diskName)->url($path);
    }

    /** Log only a generic operation label and exception class, never provider details. */
    public static function deleteBestEffort(string $diskName, ?string $path, string $operation = 'file cleanup'): void
    {
        if (!$path) {
            return;
        }

        try {
            $disk = self::disk($diskName);
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (\Throwable $exception) {
            Log::warning('Upload storage cleanup failed.', [
                'operation' => $operation,
                'exception' => $exception::class,
            ]);
        }
    }

    private static function disk(string $diskName)
    {
        self::assertConfigured($diskName);
        return Storage::disk($diskName);
    }

    private static function confirmStored(?string $path, string $diskName): string
    {
        if (!$path) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        try {
            if (!Storage::disk($diskName)->exists($path)) {
                throw new RuntimeException('The uploaded file could not be confirmed in storage.');
            }
        } catch (\Throwable $exception) {
            self::deleteBestEffort($diskName, $path, 'failed upload cleanup');
            throw new RuntimeException('The uploaded file could not be confirmed in storage.', 0, $exception);
        }

        return $path;
    }


    private static function assertConfigured(string $diskName): void
    {
        if (!in_array($diskName, ['r2-public', 'r2-private', 'b2-private'], true)) {
            return;
        }

        $settings = config("filesystems.disks.{$diskName}", []);

        $required = ['endpoint', 'key', 'secret', 'bucket'];

        if ($diskName === 'r2-public') {
            $required[] = 'url';
        }

        foreach ($required as $setting) {
            if (empty($settings[$setting] ?? null)) {
                throw new RuntimeException(
                    'Object storage is selected but required configuration is missing.'
                );
            }
        }
    }


    private static function validateDiskName(string $setting, string $diskName): void
    {
        $allowedDisks = match ($setting) {
            'public_upload_disk' => ['public', 'r2-public'],
            'private_upload_disk' => ['private', 'b2-private', 'r2-private'],
            default => [],
        };

        if (
            !in_array($diskName, $allowedDisks, true) ||
            !array_key_exists($diskName, config('filesystems.disks', []))
        ) {
            throw new RuntimeException(
                "Unsupported disk configured for {$setting}."
            );
        }

        self::assertConfigured($diskName);
    }
}
