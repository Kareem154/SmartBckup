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
