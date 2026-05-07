<?php

namespace Karim\SmartBackup\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Karim\SmartBackup\Models\Backup;
use RuntimeException;
use Spatie\Backup\Config\Config as SpatieBackupConfig;
use Throwable;

class BackupManager
{
    public function run(): BackupRunResult
    {
        $diskName = config('smart-backup.disk', 'local');

        $backup = Backup::query()->create([
            'name' => 'backup-running-' . now()->format('Y-m-d_H-i-s') . '.zip',
            'disk' => $diskName,
            'path' => '',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $temporaryDirectory = $this->temporaryDirectory();
            $originalSpatieConfig = $this->captureSpatieConfig();

            $this->configureSpatieBackup($temporaryDirectory);
            $this->configureDatabaseDumpPath();

            app()->bind('backup-temporary-project', fn () => new SmartBackupTemporaryDirectory($temporaryDirectory));

            $exitCode = Artisan::call(
                config('smart-backup.spatie_command', 'backup:run'),
                ['--config' => 'smart-backup-spatie-runtime']
            );
            $output = trim(Artisan::output());

            $this->restoreSpatieConfig($originalSpatieConfig);

            File::deleteDirectory($temporaryDirectory);

            if ($exitCode !== 0) {
                throw new RuntimeException($output !== ''
                    ? "Spatie backup command failed: {$output}"
                    : "Spatie backup command failed with exit code {$exitCode}."
                );
            }

            $latestBackupPath = app(BackupFileFinder::class)->latest();

            $latestBackupPath = app(BackupFileOrganizer::class)->reorganize($latestBackupPath);

            $finalPath = $this->moveToManagedDirectory($latestBackupPath);

            $disk = Storage::disk($diskName);

            $backup->update([
                'name' => basename($finalPath),
                'path' => $finalPath,
                'size' => $disk->exists($finalPath) ? $disk->size($finalPath) : null,
                'status' => 'completed',
                'finished_at' => now(),
                'meta' => $meta = [
                    'source_path' => $latestBackupPath,
                    'spatie_command_output' => $output,
                    'deleted_old_backups' => 0,
                ],
            ]);

            $backup = $backup->fresh();

            if ($this->shouldCleanupOldBackups()) {
                $meta['deleted_old_backups'] = app(BackupCleaner::class)->deleteOldExcept($backup);

                $backup->update([
                    'meta' => $meta,
                ]);
            }

            return BackupRunResult::fromBackup($backup->fresh());
        } catch (Throwable $e) {
            $backup->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        } finally {
            if (isset($originalSpatieConfig)) {
                $this->restoreSpatieConfig($originalSpatieConfig);
            }

            if (isset($temporaryDirectory)) {
                File::deleteDirectory($temporaryDirectory);
            }
        }
    }

    private function moveToManagedDirectory(string $sourcePath): string
    {
        $disk = Storage::disk(config('smart-backup.disk', 'local'));

        $directory = trim((string) config('smart-backup.storage_directory', 'backups'), '/');

        if ($directory === '') {
            $directory = 'backups';
        }

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $filename = basename($sourcePath);
        $destinationPath = "{$directory}/{$filename}";

        if ($sourcePath === $destinationPath) {
            return $destinationPath;
        }

        $content = $disk->get($sourcePath);
        $disk->put($destinationPath, $content);

        return $destinationPath;
    }

   private function captureSpatieConfig(): array
{
    $config = [
        'backup.backup.name' => config('backup.backup.name'),
        'backup.backup.temporary_directory' => config('backup.backup.temporary_directory'),
        'backup.backup.source.files.include' => config('backup.backup.source.files.include'),
        'backup.backup.source.files.exclude' => config('backup.backup.source.files.exclude'),
        'backup.backup.source.files.relative_path' => config('backup.backup.source.files.relative_path'),
        'smart-backup-spatie-runtime' => config('smart-backup-spatie-runtime'),
    ];

    foreach ($this->backupDatabaseConnectionNames() as $connection) {
        $config["database.connections.{$connection}"] = config("database.connections.{$connection}");
    }

    return $config;
}

    private function configureSpatieBackup(string $temporaryDirectory): void
{
    $sourcePaths = $this->backupSourcePaths();
    $excludedSourcePaths = $this->excludedSourcePaths();

    if ($sourcePaths === []) {
        throw new RuntimeException('No backup source paths exist.');
    }

    $runtimeConfig = config('backup');

    $backupFolderName = trim((string) config('smart-backup.storage_directory', 'backups'), '/');

    if ($backupFolderName === '') {
        $backupFolderName = 'backups';
    }

    data_set($runtimeConfig, 'backup.name', $backupFolderName);
    data_set($runtimeConfig, 'backup.temporary_directory', $temporaryDirectory);
    data_set($runtimeConfig, 'backup.source.files.include', $sourcePaths);
    data_set($runtimeConfig, 'backup.source.files.exclude', $excludedSourcePaths);
    data_set($runtimeConfig, 'backup.source.files.relative_path', storage_path());

    config([
        'backup.backup.name' => $backupFolderName,
        'backup.backup.temporary_directory' => $temporaryDirectory,
        'backup.backup.source.files.include' => $sourcePaths,
        'backup.backup.source.files.exclude' => $excludedSourcePaths,
        'backup.backup.source.files.relative_path' => storage_path(),
        'smart-backup-spatie-runtime' => $runtimeConfig,
    ]);

    app()->forgetInstance(SpatieBackupConfig::class);
    app()->scoped(SpatieBackupConfig::class, fn (): SpatieBackupConfig => SpatieBackupConfig::fromArray($runtimeConfig));
}

    private function configureDatabaseDumpPath(): void
    {
        $connections = collect($this->backupDatabaseConnectionNames())
            ->filter(fn (string $connection): bool => in_array(
                strtolower((string) config("database.connections.{$connection}.driver")),
                ['mysql', 'mariadb'],
                true
            ))
            ->values();

        if ($connections->isEmpty()) {
            return;
        }

        $dumpBinaryPath = app(MySqlDumpPathResolver::class)->resolve(config('smart-backup.mysql.dump_binary_path'));

        if ($dumpBinaryPath === null) {
            throw new RuntimeException('mysqldump was not found. Please install MySQL client tools or set SMART_BACKUP_MYSQL_DUMP_BINARY_PATH in your .env file. Examples: C:/xampp/mysql/bin or /usr/bin.');
        }

        foreach ($connections as $connection) {
            config([
                "database.connections.{$connection}.dump.dump_binary_path" => $dumpBinaryPath,
                "database.connections.{$connection}.dump.use_single_transaction" => filter_var(config('smart-backup.mysql.use_single_transaction', true), FILTER_VALIDATE_BOOLEAN),
                "database.connections.{$connection}.dump.timeout" => (int) config('smart-backup.mysql.dump_timeout', 300),
            ]);
        }
    }

    private function restoreSpatieConfig(array $originalSpatieConfig): void
    {
        config($originalSpatieConfig);

        app()->forgetInstance(SpatieBackupConfig::class);
        app()->scoped(SpatieBackupConfig::class, fn (): SpatieBackupConfig => SpatieBackupConfig::fromArray(config('backup')));
    }

    private function backupSourcePaths(): array
    {
        return collect(config('smart-backup.source.paths', [storage_path('app')]))
            ->filter(fn (string $path): bool => file_exists($path))
            ->values()
            ->all();
    }

    private function excludedSourcePaths(): array
    {
        return collect(config('smart-backup.source.exclude', []))
            ->filter(fn (string $path): bool => file_exists($path))
            ->values()
            ->all();
    }

    private function backupDatabaseConnectionNames(): array
    {
        return collect(config('backup.backup.source.databases', [config('database.default')]))
            ->filter(fn (mixed $connection): bool => is_string($connection) && $connection !== '')
            ->values()
            ->all();
    }

    private function temporaryDirectory(): string
    {
        $root = config('smart-backup.temporary_directory') ?: sys_get_temp_dir();

        return rtrim((string) $root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'smart-backup-'
            . now()->format('YmdHisv')
            . '-'
            . bin2hex(random_bytes(4));
    }

    private function shouldCleanupOldBackups(): bool
    {
        return ! filter_var(config('smart-backup.keep_backups', true), FILTER_VALIDATE_BOOLEAN);
    }
}
