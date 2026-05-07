<?php

namespace Karim\SmartBackup\Console;

use Illuminate\Console\Command;
use Karim\SmartBackup\Services\BackupManager;
use Throwable;

class RunSmartBackupCommand extends Command
{
    protected $signature = 'smart-backup:run';

    protected $description = 'Run a new database and storage backup.';

    public function handle(BackupManager $backupManager): int
    {
        $this->info('Starting smart backup...');

        try {
            $result = $backupManager->run();

            $this->info($result->message);
            $this->line('Name: ' . $result->name);
            $this->line('Path: ' . $result->path);
            $this->line('Disk: ' . $result->backup->disk);
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
