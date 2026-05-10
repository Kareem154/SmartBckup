<?php

namespace Karim\SmartBackup\Console;

use Illuminate\Console\Command;
use Karim\SmartBackup\Services\BackupManager;
use Throwable;

class StorageOnlySmartBackupCommand extends Command
{
    protected $signature = 'smart-backup:storage';

    protected $description = 'Run a new storage backup only.';

    public function handle(BackupManager $backupManager): int
    {
        $this->info('Starting storage-only smart backup...');

        try {
            $result = $backupManager->run(withDatabase: false, withStorage: true);

            $this->info($result->message);
            $this->line('Name: ' . $result->name);
            $this->line('Path: ' . $result->path);
            $this->line('Disk: ' . $result->backup->disk);
            $this->line('Type: ' . $result->backup->type);
            $this->line('Status: ' . $result->status);
            $this->line('Size: ' . ($result->backup->size ?? 'unknown'));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Backup failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
