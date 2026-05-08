<?php

namespace Karim\SmartBackup;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Karim\SmartBackup\Console\DeleteSmartBackupCommand;
use Karim\SmartBackup\Console\InstallSmartBackupCommand;
use Karim\SmartBackup\Console\ListSmartBackupsCommand;
use Karim\SmartBackup\Console\RunSmartBackupCommand;
use Karim\SmartBackup\Http\Controllers\BackupController;

class SmartBackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/smart-backup.php',
            'smart-backup'
        );

        /*
         * Force Spatie Backup to use Smart Backup's managed directory name
         * as early as possible.
         *
         * Without this, Spatie may keep using the host app name / APP_NAME
         * such as "zamil", then Smart Backup has to move the generated zip later.
         */
        $this->app->booting(function (): void {
            $this->forceSpatieBackupName();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/smart-backup.php' => config_path('smart-backup.php'),
        ], 'smart-backup-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_smart_backups_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_smart_backups_table.php'),
        ], 'smart-backup-migrations');

        $this->registerRouteMacro();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallSmartBackupCommand::class,
                ListSmartBackupsCommand::class,
                RunSmartBackupCommand::class,
                DeleteSmartBackupCommand::class,
            ]);
        }
    }

    /**
     * Force Spatie Laravel Backup to write directly into Smart Backup's
     * managed backup directory.
     *
     * This prevents Spatie from creating a folder named after the host
     * application, such as "zamil", before Smart Backup moves the zip.
     */
    private function forceSpatieBackupName(): void
    {
        $backupConfig = config('backup');

        if (! is_array($backupConfig)) {
            return;
        }

        $backupFolderName = trim((string) config('smart-backup.storage_directory', 'backups'), '/');

        if ($backupFolderName === '') {
            $backupFolderName = 'backups';
        }

        data_set($backupConfig, 'backup.name', $backupFolderName);

        config([
            'backup' => $backupConfig,
        ]);

        $this->app->forgetInstance(SpatieBackupConfig::class);

        $this->app->singleton(
            SpatieBackupConfig::class,
            fn (): SpatieBackupConfig => SpatieBackupConfig::fromArray(config('backup'))
        );
    }

    private function registerRouteMacro(): void
    {
        Route::macro('smartBackup', function (array $options = []): void {
            $controller = $options['controller'] ?? BackupController::class;

            $prefix = $options['prefix'] ?? 'backup';
            $name = $options['name'] ?? 'backup.';

            Route::prefix($prefix)
                ->name($name)
                ->group(function () use ($controller): void {
                    Route::get('/', [$controller, 'index'])->name('index');
                    Route::post('/', [$controller, 'store'])->name('store');
                    Route::get('/{backup}/download', [$controller, 'download'])->name('download');
                    Route::delete('/{backup}', [$controller, 'destroy'])->name('destroy');
                    Route::delete('/', [$controller, 'bulkDestroy'])->name('bulk-destroy');
                });
        });
    }
}
