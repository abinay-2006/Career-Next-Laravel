<?php

namespace App\Console\Commands;

use App\Services\StorageMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StorageInventory extends Command
{
    protected $signature = 'storage:inventory {source : Existing directory below storage/app/migration-input} {category : public-photo or private-document}';
    protected $description = 'Create a read-only file inventory for a reviewed upload backup.';

    public function handle(StorageMigrationService $service): int
    {
        try {
            $rows = $service->inventory((string) $this->argument('source'), (string) $this->argument('category'));
            $directory = storage_path('app/private/storage-migration/reports');
            File::ensureDirectoryExists($directory);
            $path = $directory.DIRECTORY_SEPARATOR.'inventory-'.now()->format('Ymd-His').'.json';
            File::put($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $this->info('Inventory written locally: '.$path);
            $this->line('File count: '.count($rows));
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
