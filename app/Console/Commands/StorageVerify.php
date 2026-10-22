<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\UploadStorage;
use Symfony\Component\Console\Exception\RuntimeException as SymfonyRuntimeException;

class StorageVerify extends Command
{
    protected $signature = 'storage:verify {--test-remote-write : Perform a write test on remote disks (requires --allow-remote)}';
    protected $description = 'Verify storage configuration and optional remote connectivity';

    public function handle()
    {
        $this->info('Verifying storage configuration...');

        // Validate public disk
        try {
            $publicDisk = UploadStorage::publicDiskName();
            $this->info("Public disk configured: {$publicDisk}");
        } catch (\RuntimeException $e) {
            $this->error('Public disk validation failed: ' . $e->getMessage());
            return self::INVALID;
        }

        // Validate private disk
        try {
            $privateDisk = UploadStorage::privateDiskName();
            $this->info("Private disk configured: {$privateDisk}");
        } catch (\RuntimeException $e) {
            $this->error('Private disk validation failed: ' . $e->getMessage());
            return self::INVALID;
        }

        // Remote write test (optional)
        if ($this->option('test-remote-write')) {
            if (!$this->option('allow-remote')) {
                $this->error('--test-remote-write requires --allow-remote');
                return self::INVALID;
            }

            if (!in_array($privateDisk, ['r2-private', 'b2-private'])) {
                $this->error('Remote write test only supported for r2-private or b2-private disks.');
                return self::INVALID;
            }

            $testKey = 'storage-verify-test-' . bin2hex(random_bytes(8));
            $disk = UploadStorage::privateDisk();

            try {
                $this->info("Writing test object: {$testKey}");
                $disk->put($testKey, 'Verification test content');

                $this->info('Reading test object back...');
                $content = $disk->get($testKey);
                if ($content !== 'Verification test content') {
                    throw new \RuntimeException('Content mismatch');
                }

                $this->info('Deleting test object...');
                $disk->delete($testKey);

                $this->info('Remote write test passed.');
            } catch (\Exception $e) {
                $this->error('Remote write test failed: ' . $e->getMessage());
                if ($disk->exists($testKey)) {
                    $this->warn('Attempting to clean up test object...');
                    try {
                        $disk->delete($testKey);
                        $this->warn('Cleanup succeeded.');
                    } catch (\Exception $cleanupE) {
                        $this->error('Cleanup failed: ' . $cleanupE->getMessage());
                    }
                }
                return self::INVALID;
            }
        }

        $this->info('Storage verification completed successfully.');
        return self::SUCCESS;
    }
}