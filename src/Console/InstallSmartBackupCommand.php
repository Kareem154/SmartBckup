<?php

namespace Karim\SmartBackup\Console;

use Illuminate\Console\Command;

class InstallSmartBackupCommand extends Command
{
    protected $signature = 'smart-backup:install';

    protected $description = 'Install Smart Backup package config and migrations.';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'smart-backup-config',
            '--force' => true,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'smart-backup-migrations',
        ]);

        $this->info('Smart Backup installed successfully.');

        return self::SUCCESS;
    }
}
