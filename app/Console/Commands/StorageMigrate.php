<?php

namespace App\Console\Commands;

use App\Services\StorageMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StorageMigrate extends Command
{
    protected $signature = 'storage:migrate-reviewed
        {source : Existing directory below storage/app/migration-input}
        {category : public-photo or private-document}
        {destination-prefix : Reviewed object-key prefix}
        {--execute : Copy files; without this option the command is a dry run}
        {--verify-only : Compare existing destination objects without copying}
        {--allow-remote-storage : Explicitly permit contacting the configured object-storage endpoint}
        {--allow-production-endpoint : Explicitly confirm a remote operation while APP_ENV=production}';
    protected $description = 'Copy reviewed uploads without deleting or overwriting source or destination files.';

    public function handle(StorageMigrationService $service): int
    {
        try {
            $execute = (bool) $this->option('execute');
            $verifyOnly = (bool) $this->option('verify-only');
            if ($execute && $verifyOnly) {
                $this->error('--execute and --verify-only cannot be combined.');
                return self::FAILURE;
            }

            $diskName = (string) config(
                $this->argument('category') === 'public-photo'
                    ? 'filesystems.public_upload_disk'
                    : 'filesystems.private_upload_disk',
                'private',
            );
            $diskConfig = config('filesystems.disks.'.$diskName, []);
            if (($diskConfig['driver'] ?? null) === 's3' && !$this->option('allow-remote-storage')) {
                $this->error('Refusing to contact remote object storage without --allow-remote-storage.');
                return self::FAILURE;
            }
            if (($diskConfig['driver'] ?? null) === 's3' && app()->environment('production') && !$this->option('allow-production-endpoint')) {
                $this->error('Refusing remote storage access in APP_ENV=production without --allow-production-endpoint.');
                return self::FAILURE;
            }

            $rows = $service->migrate(
                (string) $this->argument('source'),
                (string) $this->argument('category'),
                (string) $this->argument('destination-prefix'),
                $execute,
                $verifyOnly,
            );

            $directory = storage_path('app/private/storage-migration/reports');
            File::ensureDirectoryExists($directory);
            $path = $directory.'/migration-'.now()->format('Ymd-His').'.json';
            File::put($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $counts = array_count_values(array_column($rows, 'destination_status'));
            $this->table(['Status', 'Count'], collect($counts)->map(fn ($count, $status) => [$status, $count])->values()->all());
            $this->info('Report written locally: '.$path);

            foreach (['failed', 'verification_failed', 'missing_source'] as $failure) {
                if (($counts[$failure] ?? 0) > 0) {
                    return self::FAILURE;
                }
            }
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Storage migration could not continue ('.$exception::class.').');
            return self::FAILURE;
        }
    }
}
