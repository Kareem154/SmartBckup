<?php

namespace Karim\SmartBackup\Tests;

use Illuminate\Foundation\Application;
use Karim\SmartBackup\SmartBackupServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Backup\BackupServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            BackupServiceProvider::class,
            SmartBackupServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use SQLite in-memory for database tests
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Use local disk for storage tests
        $app['config']->set('filesystems.disks.local.root', storage_path('app'));

        // Minimal smart-backup config
        $app['config']->set('smart-backup.disk', 'local');
        $app['config']->set('smart-backup.storage_directory', 'backups');
        $app['config']->set('smart-backup.reorganize_zip', false);
        $app['config']->set('smart-backup.source.paths', [storage_path('app')]);
        $app['config']->set('smart-backup.source.exclude', [
            storage_path('app/backups'),
            storage_path('app/private/backups'),
            storage_path('framework'),
            storage_path('logs'),
        ]);

        // Disable mail notifications
        $app['config']->set('mail.default', 'log');

        // Basic Spatie backup config
        $app['config']->set('backup.backup.source.databases', ['testing']);
        $app['config']->set('backup.backup.destination.disks', ['local']);
        $app['config']->set('backup.notifications.notifications', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function defineRoutes($router): void
    {
        \Illuminate\Support\Facades\Route::smartBackup();
    }
}
