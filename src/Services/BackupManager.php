<?php

namespace Karim\SmartBackup\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Karim\SmartBackup\Models\Backup;
use Karim\SmartBackup\Enums\BackupType;
use RuntimeException;
use Spatie\Backup\Config\Config as SpatieBackupConfig;
use Throwable;

class BackupManager
{
    /**
     * Run a new smart backup.
     *
     * This method creates a database record, configures Spatie Laravel Backup
     * at runtime, runs the backup command, then stores the final backup metadata.
     *
     * The host Laravel application should not need to manually configure
     * mysqldump inside config/database.php. Smart Backup tries to detect and
     * inject the dump binary path automatically before Spatie runs.
     *
     * @throws \Throwable
     */
    public function run(bool $withDatabase = true, bool $withStorage = true): BackupRunResult
    {
        if (! $withDatabase && ! $withStorage) {
            throw new RuntimeException('At least one backup source must be enabled.');
        }

        $type = $withDatabase && $withStorage ? BackupType::FULL : ($withDatabase ? BackupType::DATABASE : BackupType::STORAGE);
        $prefix = $type->value . '-backup-';

        $diskName = config('smart-backup.disk', 'local');

        $backup = Backup::query()->create([
            'name' => $prefix . now()->format('Y-m-d_H-i-s') . '.zip',
            'disk' => $diskName,
            'path' => '',
            'type' => $type->value,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $temporaryDirectory = $this->temporaryDirectory();
            $originalSpatieConfig = $this->captureSpatieConfig();

            /*
             * Configure the database dump path before building Spatie's runtime
             * config. This ensures Spatie receives the updated database connection
             * dump settings when it creates the backup job.
             */
            if ($withDatabase) {
                $this->configureDatabaseDumpPath();
            }

            $this->configureSpatieBackup($temporaryDirectory, $withDatabase, $withStorage);

            /*
             * Spatie resolves this binding internally for its temporary project
             * directory. We override it to prevent Spatie from deleting the
             * temporary directory before Smart Backup finishes processing.
             */
            app()->bind(
                'backup-temporary-project',
                fn () => new SmartBackupTemporaryDirectory($temporaryDirectory)
            );

            app()->forgetInstance(SpatieBackupConfig::class);

            app()->instance(
                SpatieBackupConfig::class,
                SpatieBackupConfig::fromArray(config('backup'))
            );

            $options = [];
            if (! $withDatabase) {
                $options['--only-files'] = true;
            }
            if (! $withStorage) {
                $options['--only-db'] = true;
            }

            $exitCode = Artisan::call(
                config('smart-backup.spatie_command', 'backup:run'),
                $options
            );

            $output = trim(Artisan::output());

            /*
             * Some Spatie versions may return a successful console exit code
             * while still printing a backup failure message. We detect both the
             * exit code and known failure text to avoid hiding the real error
             * behind "No backup zip files found".
             */
            if (
                $exitCode !== 0
                || str_contains($output, 'Backup failed because')
                || str_contains($output, 'The dump process failed')
            ) {
                throw new RuntimeException($output !== ''
                    ? "Spatie backup command failed: {$output}"
                    : "Spatie backup command failed with exit code {$exitCode}."
                );
            }

            try {
                $latestBackupPath = app(BackupFileFinder::class)->latest();
            } catch (RuntimeException $e) {
                throw new RuntimeException(
                    "Smart Backup could not find the generated backup zip.\n\n".
                    "Spatie output:\n".
                    ($output !== '' ? $output : '[empty output]')."\n\n".
                    "Finder error:\n".
                    $e->getMessage()
                );
            }

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
            /*
             * Always restore the original config and remove temporary files,
             * whether the backup succeeds or fails.
             */
            if (isset($originalSpatieConfig)) {
                $this->restoreSpatieConfig($originalSpatieConfig);
            }

            if (isset($temporaryDirectory)) {
                File::deleteDirectory($temporaryDirectory);
            }
        }
    }

    /**
     * Move the generated backup zip to Smart Backup's managed directory.
     *
     * Spatie may generate the zip inside a folder named after the host application.
     * Smart Backup keeps only the managed copy and removes the original generated
     * file to avoid duplicated backup folders.
     *
     * Strategy (in order of preference):
     *
     * 1. disk->move()  — atomic rename, zero RAM, instant. Works on local disks.
     * 2. Stream fallback — pipes the file chunk-by-chunk using readStream /
     *    writeStream. Zero RAM, compatible with S3, FTP, and remote disks that
     *    do not support atomic renames.
     *
     * Both strategies are memory-safe and support backup files of any size.
     */
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

        /*
         * Attempt 1: atomic filesystem rename.
         * Uses zero RAM and is instant on local disks.
         * Some cloud/remote drivers (S3, FTP) may return false here.
         */
        if ($disk->move($sourcePath, $destinationPath)) {
            $this->deleteEmptyParentDirectory($sourcePath, $directory);

            return $destinationPath;
        }

        /*
         * Attempt 2: stream-based fallback (memory-safe copy + delete).
         *
         * readStream() opens the source as a PHP resource stream and
         * writeStream() pipes it to the destination chunk-by-chunk, never
         * loading the full file into RAM. This works correctly on:
         *   - Amazon S3
         *   - FTP / SFTP
         *   - Any Flysystem-compatible remote driver
         */
        $readStream = $disk->readStream($sourcePath);

        if (! is_resource($readStream)) {
            throw new RuntimeException(
                "Unable to open source backup file as a stream: {$sourcePath}"
            );
        }

        try {
            $disk->writeStream($destinationPath, $readStream);
        } finally {
            if (is_resource($readStream)) {
                fclose($readStream);
            }
        }

        if (! $disk->exists($destinationPath)) {
            throw new RuntimeException(
                "Stream copy succeeded but destination file was not found: {$destinationPath}"
            );
        }

        $disk->delete($sourcePath);

        $this->deleteEmptyParentDirectory($sourcePath, $directory);

        return $destinationPath;
    }

    /**
     * Delete the source parent directory if it becomes empty.
     *
     * This removes folders like "zamil" after moving the zip to "backups",
     * but it never deletes the managed backup directory itself.
     */
    private function deleteEmptyParentDirectory(string $sourcePath, string $managedDirectory): void
    {
        $disk = Storage::disk(config('smart-backup.disk', 'local'));

        $parentDirectory = trim(dirname($sourcePath), '/\\.');

        if (
            $parentDirectory === ''
            || $parentDirectory === '.'
            || $parentDirectory === $managedDirectory
        ) {
            return;
        }

        if (! $disk->exists($parentDirectory)) {
            return;
        }

        if ($disk->allFiles($parentDirectory) !== []) {
            return;
        }

        if ($disk->directories($parentDirectory) !== []) {
            return;
        }

        $disk->deleteDirectory($parentDirectory);
    }

    /**
     * Capture the original config values that Smart Backup changes at runtime.
     *
     * @return array<string, mixed>
     */
    private function captureSpatieConfig(): array
    {
        $config = [
            'backup' => config('backup'),
            'smart-backup-spatie-runtime' => config('smart-backup-spatie-runtime'),
        ];

        foreach ($this->backupDatabaseConnectionNames() as $connection) {
            $config["database.connections.{$connection}"] = config("database.connections.{$connection}");
        }

        return $config;
    }

    /**
     * Configure Spatie Laravel Backup for the current Smart Backup run.
     *
     * This method changes Spatie's backup name, temporary directory, source
     * paths, excluded paths, relative path, and database list at runtime.
     */
    private function configureSpatieBackup(string $temporaryDirectory, bool $withDatabase, bool $withStorage): void
    {
        $sourcePaths = $withStorage ? $this->backupSourcePaths() : [];
        $excludedSourcePaths = $withStorage ? $this->excludedSourcePaths() : [];

        if ($withStorage && $sourcePaths === []) {
            throw new RuntimeException('No backup source paths exist.');
        }

        $runtimeConfig = config('backup');

        $backupFolderName = trim((string) config('smart-backup.storage_directory', 'backups'), '/');

        if ($backupFolderName === '') {
            $backupFolderName = 'backups';
        }

        /*
         * Force Spatie to create backups directly inside Smart Backup's managed
         * directory instead of using the host application's APP_NAME.
         */
        data_set($runtimeConfig, 'backup.name', $backupFolderName);
        data_set($runtimeConfig, 'backup.temporary_directory', str_replace('\\', '/', $temporaryDirectory));
        data_set($runtimeConfig, 'backup.source.files.include', array_map(fn($p) => str_replace('\\', '/', $p), $sourcePaths));
        data_set($runtimeConfig, 'backup.source.files.exclude', array_map(fn($p) => str_replace('\\', '/', $p), $excludedSourcePaths));
        data_set($runtimeConfig, 'backup.source.files.relative_path', str_replace('\\', '/', storage_path()));
        data_set($runtimeConfig, 'backup.source.databases', $withDatabase ? $this->backupDatabaseConnectionNames() : []);

        /*
         * Important:
         * Set the full "backup" config root, not only nested keys.
         * This prevents Spatie from reading stale values such as APP_NAME/zamil.
         */
        config([
            'backup' => $runtimeConfig,
            'smart-backup-spatie-runtime' => $runtimeConfig,
        ]);

        /*
         * Force Spatie to use the fresh runtime config object.
         * Using instance() is stronger here than scoped(), because the console
         * command may resolve the config from the container during the same run.
         */
        app()->forgetInstance(SpatieBackupConfig::class);

        app()->instance(
            SpatieBackupConfig::class,
            SpatieBackupConfig::fromArray($runtimeConfig)
        );
    }

    /**
     * Detect and inject the mysqldump path for MySQL/MariaDB connections.
     *
     * Smart Backup tries to find mysqldump automatically using:
     * - SMART_BACKUP_MYSQL_DUMP_BINARY_PATH
     * - MYSQL_DUMP_BINARY_PATH
     * - system PATH
     * - common local/server paths such as XAMPP, Laragon, WAMP, /usr/bin
     */
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

        $dumpBinaryPath = app(MySqlDumpPathResolver::class)
            ->resolve(config('smart-backup.mysql.dump_binary_path'));

        if ($dumpBinaryPath === null) {
            throw new RuntimeException(
                'mysqldump was not found. Smart Backup tried to detect it automatically, but could not find it. '.
                'Install MySQL client tools or set SMART_BACKUP_MYSQL_DUMP_BINARY_PATH. '.
                'Examples: C:/xampp/mysql/bin or /usr/bin.'
            );
        }

        foreach ($connections as $connection) {
            $connectionConfig = config("database.connections.{$connection}", []);

            $connectionConfig['dump'] = array_merge($connectionConfig['dump'] ?? [], [
                'dump_binary_path' => $dumpBinaryPath,
                'use_single_transaction' => filter_var(
                    config('smart-backup.mysql.use_single_transaction', true),
                    FILTER_VALIDATE_BOOLEAN
                ),
                'timeout' => (int) config('smart-backup.mysql.dump_timeout', 300),
            ]);

            config([
                "database.connections.{$connection}" => $connectionConfig,
            ]);
        }
    }

    /**
     * Restore Spatie and database config values after a backup run.
     *
     * @param  array<string, mixed>  $originalSpatieConfig
     */
    private function restoreSpatieConfig(array $originalSpatieConfig): void
    {
        config($originalSpatieConfig);

        app()->forgetInstance(SpatieBackupConfig::class);

        app()->instance(
            SpatieBackupConfig::class,
            SpatieBackupConfig::fromArray(config('backup'))
        );
    }

    /**
     * Get backup source paths that currently exist.
     *
     * @return array<int, string>
     */
    private function backupSourcePaths(): array
    {
        return collect(config('smart-backup.source.paths', [storage_path('app')]))
            ->filter(fn (string $path): bool => file_exists($path))
            ->values()
            ->all();
    }

    /**
     * Get excluded source paths that currently exist.
     *
     * @return array<int, string>
     */
    private function excludedSourcePaths(): array
    {
        return collect(config('smart-backup.source.exclude', []))
            ->filter(fn (string $path): bool => file_exists($path))
            ->values()
            ->all();
    }

    /**
     * Get the database connections that should be included in the backup.
     *
     * If Spatie's database list is empty or invalid, Smart Backup falls back
     * to the default Laravel database connection.
     *
     * @return array<int, string>
     */
    private function backupDatabaseConnectionNames(): array
    {
        $connections = config('backup.backup.source.databases');

        if (! is_array($connections) || $connections === []) {
            $connections = [config('database.default')];
        }

        return collect($connections)
            ->filter(fn (mixed $connection): bool => is_string($connection) && $connection !== '')
            ->values()
            ->all();
    }

    /**
     * Build a unique temporary directory path for the current backup run.
     *
     *
     * @throws \Random\RandomException
     */
    private function temporaryDirectory(): string
    {
        $root = config('smart-backup.temporary_directory') ?: sys_get_temp_dir();

        return rtrim((string) $root, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'smart-backup-'
            .now()->format('YmdHisv')
            .'-'
            .bin2hex(random_bytes(4));
    }

    /**
     * Determine whether old backups should be deleted after a successful run.
     */
    private function shouldCleanupOldBackups(): bool
    {
        return ! filter_var(config('smart-backup.keep_backups', true), FILTER_VALIDATE_BOOLEAN);
    }
}
