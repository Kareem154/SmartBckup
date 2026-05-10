<?php

namespace Karim\SmartBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Karim\SmartBackup\Enums\BackupType;
use Karim\SmartBackup\Services\BackupManager;

class RunSmartBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     * Backups might take a while, so we give it an hour by default.
     */
    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(public BackupType $type)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(BackupManager $backupManager): void
    {
        match ($this->type) {
            BackupType::DATABASE => $backupManager->run(withDatabase: true, withStorage: false),
            BackupType::STORAGE => $backupManager->run(withDatabase: false, withStorage: true),
            default => $backupManager->run(withDatabase: true, withStorage: true),
        };
    }
}
