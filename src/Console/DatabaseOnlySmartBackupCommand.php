<?php

namespace Karim\SmartBackup\Console;

use Illuminate\Console\Command;
use Karim\SmartBackup\Services\BackupManager;
use Throwable;

class DatabaseOnlySmartBackupCommand extends Command
{
    protected $signature = 'smart-backup:database';

    protected $description = 'Run a new database backup only.';

    public function handle(BackupManager $backupManager): int
    {
        $this->info('Starting database-only smart backup...');

        try {
            $result = $backupManager->run(withDatabase: true, withStorage: false);

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
