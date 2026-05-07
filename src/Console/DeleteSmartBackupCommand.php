<?php

namespace Karim\SmartBackup\Console;

use Illuminate\Console\Command;
use Karim\SmartBackup\Models\Backup;
use Karim\SmartBackup\Services\BackupCleaner;
use RuntimeException;

class DeleteSmartBackupCommand extends Command
{
    protected $signature = 'smart-backup:delete {id} {--force}';

    protected $description = 'Delete a Smart Backup record and its backup file.';

    public function handle(BackupCleaner $backupCleaner): int
    {
        $backup = Backup::query()->find($this->argument('id'));

        if (! $backup) {
            $this->error('Backup not found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Delete backup [{$backup->id}] {$backup->name}?")) {
            $this->warn('Deletion cancelled.');

            return self::FAILURE;
        }

        try {
            $backupCleaner->delete($backup);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup deleted successfully.');

        return self::SUCCESS;
    }
}
