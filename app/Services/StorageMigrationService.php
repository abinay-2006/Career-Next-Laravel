<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/** Read-only inventory and copy-only migration helpers for reviewed backups. */
class StorageMigrationService
{
    public function inventory(string $source, string $category): array
    {
        $this->assertCategory($category);
        $root = $this->approvedRoot($source);
        $rows = [];

        foreach ((new Finder())->files()->in($root)->ignoreDotFiles(false)->sortByName() as $file) {
            $absolute = $file->getPathname();
            if (is_link($absolute) || !is_file($absolute)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
            $rows[] = [
                'source' => $relative,
                'category' => $category,
                'bytes' => filesize($absolute),
                'mime' => (new \finfo(FILEINFO_MIME_TYPE))->file($absolute) ?: 'application/octet-stream',
                'sha256' => hash_file('sha256', $absolute),
                'reference_status' => 'not_checked',
                'destination_status' => 'not_checked',
            ];
        }

        return $rows;
    }

    /**
     * Copy only. A destination key is never overwritten; retries verify matching
     * size and SHA-256 before reporting already_verified.
     */
    public function migrate(string $source, string $category, string $destinationPrefix, bool $execute, bool $verifyOnly = false): array
    {
        $root = $this->approvedRoot($source);
        $diskName = $this->destinationDisk($category);
        $prefix = $this->safeKey($destinationPrefix);
        $disk = Storage::disk($diskName);
        $rows = [];

        foreach ($this->inventory($root, $category) as $row) {
            $key = $prefix.'/'.$row['source'];
            $row['destination'] = $key;
            try {
                $row['destination_status'] = $this->migrateOne(
                    $root,
                    $row,
                    $key,
                    $disk,
                    $execute,
                    $verifyOnly,
                );
            } catch (\Throwable $exception) {
                $row['destination_status'] = 'failed';
                $row['error'] = $exception::class;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function migrateOne(string $root, array $row, string $key, $disk, bool $execute, bool $verifyOnly): string
    {
        if ($disk->exists($key)) {
            $matches = $this->matches($disk, $key, (int) $row['bytes'], $row['sha256']);
            if ($matches) {
                return 'already_verified';
            }
            return $verifyOnly ? 'verification_failed' : 'skipped';
        }

        if ($verifyOnly) {
            return 'verification_failed';
        }
        if (!$execute) {
            return 'skipped';
        }

        $sourcePath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $row['source']);
        $stream = fopen($sourcePath, 'rb');
        if ($stream === false) {
            return 'missing_source';
        }
        try {
            if (!$disk->writeStream($key, $stream)) {
                return 'failed';
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $this->matches($disk, $key, (int) $row['bytes'], $row['sha256'])
            ? 'copied'
            : 'verification_failed';
    }

    private function matches($disk, string $key, int $expectedBytes, string $expectedHash): bool
    {
        $stream = $disk->readStream($key);
        if ($stream === false) {
            return false;
        }

        $hash = hash_init('sha256');
        $bytes = 0;
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    return false;
                }
                $bytes += strlen($chunk);
                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }

        return $bytes === $expectedBytes && hash_final($hash) === $expectedHash;
    }

    private function approvedRoot(string $source): string
    {
        $allowed = realpath(storage_path('app/migration-input'));
        $root = realpath($source);
        if (!$allowed || !$root || !str_starts_with($root.DIRECTORY_SEPARATOR, $allowed.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Source must be an existing directory under storage/app/migration-input.');
        }

        return $root;
    }

    private function destinationDisk(string $category): string
    {
        return match ($category) {
            'public-photo' => (string) config('filesystems.public_upload_disk', 'public'),
            'private-document' => (string) config('filesystems.private_upload_disk', 'private'),
            default => throw new RuntimeException('Category must be public-photo or private-document.'),
        };
    }

    private function assertCategory(string $category): void
    {
        if (!in_array($category, ['public-photo', 'private-document'], true)) {
            throw new RuntimeException('Category must be public-photo or private-document.');
        }
    }

    private function safeKey(string $key): string
    {
        $key = trim(str_replace('\\', '/', $key), '/');
        if ($key === '' || preg_match('#(^|/)\.\.?(/|$)#', $key) || str_contains($key, "\0")) {
            throw new RuntimeException('Destination prefix is invalid.');
        }

        return $key;
    }
}
