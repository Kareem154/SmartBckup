# Smart Backup

`karim/smart-backup` is a reusable Laravel package for creating and managing backup records and backup archives.

It is designed to give a Laravel application a simple backend layer for:

- creating backups
- listing backup records
- downloading backup files
- deleting one backup
- deleting multiple backups
- publishing package config and migration files

The package uses [`spatie/laravel-backup`](https://github.com/spatie/laravel-backup) internally to create the backup archive, then stores and manages backup records through its own database table.

> Smart Backup does not ship Blade views, dashboard screens, UI components, or authorization rules. Those parts belong to the host Laravel application.

---

## Features

- Create database and storage backups.
- Store backup metadata in a dedicated database table.
- Download backup archives through a controller route.
- Delete a single backup file and its database record.
- Bulk delete multiple backup files and their database records.
- Prevent deleting the last backup when enabled.
- Optional cleanup of older backups after a new backup is created.
- Runtime MySQL/MariaDB `mysqldump` path detection.
- Works as a reusable Laravel package.
- Provides Artisan commands.
- Provides a route macro for easy host application integration.
- Does not force any dashboard layout, middleware, prefix, or authentication guard.

---

## What Gets Backed Up

By default, the package is configured to back up:

- the configured database dump handled by Spatie Laravel Backup
- `storage/app`
- `storage/demo` if that directory exists

The package does **not** back up the full Laravel project.

It does not include project root files or directories such as:

- `.env`
- `composer.json`
- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `resources/`
- `routes/`
- `vendor/`
- `storage/framework`
- `storage/logs`

Restore is **not implemented** in `v1.0.0`.

---

## Requirements

- PHP `^8.2`
- Laravel `^11.0`, `^12.0`, or `^13.0` depending on your package `composer.json`
- `spatie/laravel-backup`
- MySQL/MariaDB client tools if you want database dumps for MySQL or MariaDB
- `ZipArchive` PHP extension
- A writable storage disk

For MySQL or MariaDB backups, the server must be able to run `mysqldump`.

---

## Installation

Install the package using Composer:

```bash
composer require karim/smart-backup:^1.0
```

Publish the configuration file and migration:

```bash
php artisan smart-backup:install
```

Run the migration:

```bash
php artisan migrate
```

This creates the package database table, usually:

```text
smart_backups
```

---

## Publishing Files Manually

The install command publishes both the config file and the migration:

```bash
php artisan smart-backup:install
```

You can also publish them separately.

Publish the config file only:

```bash
php artisan vendor:publish --tag=smart-backup-config
```

This publishes:

```text
config/smart-backup.php
```

Publish the migration only:

```bash
php artisan vendor:publish --tag=smart-backup-migrations
```

Then run:

```bash
php artisan migrate
```

---

## Configuration

After publishing the config file, you can edit:

```text
config/smart-backup.php
```

Default configuration example:

```php
<?php

return [

    'disk' => env('SMART_BACKUP_DISK', 'local'),

    'storage_directory' => env('SMART_BACKUP_DIRECTORY', 'backups'),

    'temporary_directory' => env('SMART_BACKUP_TEMPORARY_DIRECTORY'),

    'source_folder' => env('SMART_BACKUP_SOURCE_FOLDER'),

    'source' => [
        'paths' => [
            storage_path('app'),
            storage_path('demo'),
        ],

        'exclude' => [
            storage_path('app/backups'),
            storage_path('app/private/backups'),
            storage_path('app/backup-temp'),
            storage_path('app/smart-backup-temp'),
            storage_path('framework'),
            storage_path('logs'),
        ],
    ],

    'keep_backups' => env('SMART_BACKUP_KEEP_BACKUPS', true),

    'prevent_delete_last_backup' => env('SMART_BACKUP_PREVENT_DELETE_LAST', true),

    'reorganize_zip' => env('SMART_BACKUP_REORGANIZE_ZIP', true),

    'project_folder_prefix' => env('SMART_BACKUP_PROJECT_FOLDER_PREFIX', 'backup-project'),

    'database' => [
        'table' => 'smart_backups',
    ],

    'mysql' => [
        'dump_binary_path' => env('SMART_BACKUP_MYSQL_DUMP_BINARY_PATH', env('MYSQL_DUMP_BINARY_PATH')),
        'dump_timeout' => env('SMART_BACKUP_MYSQL_DUMP_TIMEOUT', 300),
        'use_single_transaction' => env('SMART_BACKUP_MYSQL_SINGLE_TRANSACTION', true),
    ],

    'spatie_command' => env('SMART_BACKUP_SPATIE_COMMAND', 'backup:run'),

    'restore' => [
        'enabled' => false,
    ],

];
```

---

## Environment Variables

You can configure the package using the following `.env` variables:

```env
SMART_BACKUP_DISK=local
SMART_BACKUP_DIRECTORY=backups
SMART_BACKUP_TEMPORARY_DIRECTORY=C:/tmp/smart-backup-temp
SMART_BACKUP_SOURCE_FOLDER=
SMART_BACKUP_KEEP_BACKUPS=true
SMART_BACKUP_PREVENT_DELETE_LAST=true
SMART_BACKUP_REORGANIZE_ZIP=true
SMART_BACKUP_PROJECT_FOLDER_PREFIX=backup-project
SMART_BACKUP_SPATIE_COMMAND=backup:run
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=
SMART_BACKUP_MYSQL_DUMP_TIMEOUT=300
SMART_BACKUP_MYSQL_SINGLE_TRANSACTION=true
```

### `SMART_BACKUP_DISK`

The Laravel filesystem disk where backup archives are stored.

```env
SMART_BACKUP_DISK=local
```

### `SMART_BACKUP_DIRECTORY`

The directory inside the selected disk where managed backup files are stored.

```env
SMART_BACKUP_DIRECTORY=backups
```

### `SMART_BACKUP_TEMPORARY_DIRECTORY`

Optional temporary directory used while creating backups.

```env
SMART_BACKUP_TEMPORARY_DIRECTORY=C:/tmp/smart-backup-temp
```

On Linux/VPS:

```env
SMART_BACKUP_TEMPORARY_DIRECTORY=/tmp/smart-backup-temp
```

### `SMART_BACKUP_SOURCE_FOLDER`

Optional source folder used when locating the latest backup created by Spatie.

If empty, the package uses `SMART_BACKUP_DIRECTORY`.

```env
SMART_BACKUP_SOURCE_FOLDER=
```

### `SMART_BACKUP_KEEP_BACKUPS`

Controls whether old backup records/files should be kept after creating a new backup.

```env
SMART_BACKUP_KEEP_BACKUPS=true
```

If set to `false`, old backups are deleted after a successful new backup, except the current one.

```env
SMART_BACKUP_KEEP_BACKUPS=false
```

### `SMART_BACKUP_PREVENT_DELETE_LAST`

Prevents deleting the final remaining backup record.

```env
SMART_BACKUP_PREVENT_DELETE_LAST=true
```

If set to `false`, users can delete all backups.

```env
SMART_BACKUP_PREVENT_DELETE_LAST=false
```

### `SMART_BACKUP_REORGANIZE_ZIP`

Controls whether the package should reorganize the generated ZIP structure.

```env
SMART_BACKUP_REORGANIZE_ZIP=true
```

### `SMART_BACKUP_PROJECT_FOLDER_PREFIX`

The folder prefix used inside the reorganized ZIP archive.

```env
SMART_BACKUP_PROJECT_FOLDER_PREFIX=backup-project
```

### `SMART_BACKUP_SPATIE_COMMAND`

The Spatie Artisan command used internally.

```env
SMART_BACKUP_SPATIE_COMMAND=backup:run
```

### `SMART_BACKUP_MYSQL_DUMP_BINARY_PATH`

Path to the folder that contains `mysqldump`.

Windows XAMPP example:

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=C:/xampp/mysql/bin
```

Linux/VPS example:

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=/usr/bin
```

A full executable path is also accepted:

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=C:/xampp/mysql/bin/mysqldump.exe
```

But the folder path is preferred.

### `SMART_BACKUP_MYSQL_DUMP_TIMEOUT`

Database dump timeout in seconds.

```env
SMART_BACKUP_MYSQL_DUMP_TIMEOUT=300
```

### `SMART_BACKUP_MYSQL_SINGLE_TRANSACTION`

Enables single transaction mode for MySQL/MariaDB dumps.

```env
SMART_BACKUP_MYSQL_SINGLE_TRANSACTION=true
```

---

## MySQL and MariaDB Dump Detection

Database backups for MySQL and MariaDB require the `mysqldump` client binary.

Smart Backup attempts to resolve the dump binary path automatically.

Detection order:

1. `SMART_BACKUP_MYSQL_DUMP_BINARY_PATH`
2. `MYSQL_DUMP_BINARY_PATH`
3. System `PATH`
   - Windows: `where mysqldump`
   - Linux/macOS: `command -v mysqldump`
4. Common local and server paths:
   - `C:/xampp/mysql/bin`
   - Laragon MySQL paths
   - WAMP MySQL paths
   - MySQL program folders
   - MariaDB program folders
   - `/usr/bin`
   - `/usr/local/bin`
   - `/usr/local/mysql/bin`

### Windows XAMPP Example

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=C:/xampp/mysql/bin
```

### Linux/VPS Example

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=/usr/bin
```

### cPanel Example

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=/usr/bin
```

On cPanel or shared hosting, make sure:

- `mysqldump` is available.
- PHP is allowed to run process commands.
- Your hosting provider has not disabled required PHP functions.

Disabled functions such as the following may prevent database dumps:

- `exec`
- `shell_exec`
- `proc_open`
- `proc_get_status`

---

## Artisan Commands

### Install the Package Files

Publishes the config and migration files:

```bash
php artisan smart-backup:install
```

### Create a Backup

Creates a new backup archive and database record:

```bash
php artisan smart-backup:run
```

### List Backups

Displays backup records in the console:

```bash
php artisan smart-backup:list
```

### Delete a Backup

Deletes one backup file and its database record:

```bash
php artisan smart-backup:delete {id}
```

Example:

```bash
php artisan smart-backup:delete 5
```

Delete without confirmation:

```bash
php artisan smart-backup:delete 5 --force
```

Deletion respects:

```env
SMART_BACKUP_PREVENT_DELETE_LAST=true
```

---

## Routes

The package registers a Laravel route macro.

It does not automatically load routes. You decide where and how to register them.

Add this to your host application's route file:

```php
use Illuminate\Support\Facades\Route;

Route::smartBackup();
```

Default generated routes:

```text
GET     /backup                    backup.index
POST    /backup                    backup.store
GET     /backup/{backup}/download  backup.download
DELETE  /backup/{backup}           backup.destroy
DELETE  /backup                    backup.bulk-destroy
```

---

## Custom Route Prefix and Names

You can customize the route prefix and route names:

```php
use Illuminate\Support\Facades\Route;

Route::smartBackup([
    'prefix' => 'backups',
    'name' => 'backups.',
]);
```

This generates routes such as:

```text
GET     /backups                    backups.index
POST    /backups                    backups.store
GET     /backups/{backup}/download  backups.download
DELETE  /backups/{backup}           backups.destroy
DELETE  /backups                    backups.bulk-destroy
```

---

## Dashboard Integration Example

The package does not assume a dashboard prefix or authentication middleware.

You can register the routes inside your own dashboard group:

```php
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')
    ->name('dashboard.')
    ->middleware(['web', 'auth'])
    ->group(function () {
        Route::smartBackup();
    });
```

This generates:

```text
GET     /dashboard/backup                    dashboard.backup.index
POST    /dashboard/backup                    dashboard.backup.store
GET     /dashboard/backup/{backup}/download  dashboard.backup.download
DELETE  /dashboard/backup/{backup}           dashboard.backup.destroy
DELETE  /dashboard/backup                    dashboard.backup.bulk-destroy
```

You can also use an admin guard:

```php
Route::prefix('dashboard')
    ->name('dashboard.')
    ->middleware(['web', 'auth:admin'])
    ->group(function () {
        Route::smartBackup();
    });
```

---

## API Usage

### List Backups

```http
GET /backup
Accept: application/json
```

Optional pagination:

```http
GET /backup?per_page=20
Accept: application/json
```

### Create Backup

```http
POST /backup
Accept: application/json
```

Successful response example:

```json
{
    "success": true,
    "message": "تم إنشاء النسخة الاحتياطية بنجاح.",
    "result": {
        "path": "backups/backup-2026-05-08.zip",
        "name": "backup-2026-05-08.zip",
        "status": "completed",
        "message": "Backup completed successfully."
    },
    "data": {
        "id": 1,
        "name": "backup-2026-05-08.zip",
        "disk": "local",
        "path": "backups/backup-2026-05-08.zip",
        "size": 1048576,
        "status": "completed"
    }
}
```

### Download Backup

```http
GET /backup/{backup}/download
```

Example:

```http
GET /backup/1/download
```

In Postman, use:

```text
Send and Download
```

If the file is missing, the package returns:

```json
{
    "success": false,
    "message": "ملف النسخة الاحتياطية غير موجود."
}
```

### Delete One Backup

```http
DELETE /backup/{backup}
Accept: application/json
```

Example:

```http
DELETE /backup/1
Accept: application/json
```

Successful response:

```json
{
    "success": true,
    "message": "تم حذف النسخة الاحتياطية بنجاح."
}
```

### Bulk Delete Backups

```http
DELETE /backup
Accept: application/json
Content-Type: application/json
```

Request body:

```json
{
    "ids": [1, 2, 3]
}
```

Successful response:

```json
{
    "success": true,
    "message": "تم حذف النسخ الاحتياطية المحددة بنجاح.",
    "deleted_count": 3
}
```

If no IDs are sent:

```json
{
    "success": false,
    "message": "لم يتم تحديد أي نسخ احتياطية للحذف."
}
```

If no matching backups are found:

```json
{
    "success": false,
    "message": "لم يتم العثور على أي نسخ احتياطية مطابقة.",
    "deleted_count": 0
}
```

> Note: Bulk delete uses a `DELETE` request with a JSON body. Laravel supports this, but make sure your frontend/client sends the `Content-Type: application/json` header.

---

## Backup Database Table

The package stores backup records in a configurable table.

Default table name:

```text
smart_backups
```

The model reads the table name from:

```php
config('smart-backup.database.table', 'smart_backups')
```

Typical stored fields include:

- `id`
- `name`
- `disk`
- `path`
- `size`
- `status`
- `error_message`
- `meta`
- `started_at`
- `finished_at`
- `created_at`
- `updated_at`

The exact columns depend on the published migration file.

---

## Backup Statuses

Common statuses:

```text
running
completed
failed
```

When a backup starts, a record is created with:

```text
running
```

When it succeeds, it becomes:

```text
completed
```

When it fails, it becomes:

```text
failed
```

The error message is stored in the backup record.

---

## ZIP Structure

When `SMART_BACKUP_REORGANIZE_ZIP=true`, the package can reorganize the generated ZIP archive.

Database dumps remain under:

```text
db-dumps/
```

Project storage files are placed under a project folder prefix:

```text
backup-project-{backup-file-name}/
```

You can customize the prefix:

```env
SMART_BACKUP_PROJECT_FOLDER_PREFIX=backup-project
```

Disable ZIP reorganization:

```env
SMART_BACKUP_REORGANIZE_ZIP=false
```

---

## Filesystem Disk Notes

By default:

```env
SMART_BACKUP_DISK=local
```

This usually means backups are stored under:

```text
storage/app/backups
```

If you use another disk, make sure:

- the disk exists in `config/filesystems.php`
- the disk is writable
- the disk supports reading, writing, deleting, and downloading files

---

## Local Path Development

If you are developing the package locally before publishing it to Packagist, you can use Composer path repositories.

In the host application `composer.json`:

```json
{
    "require": {
        "karim/smart-backup": "^1.0"
    },
    "repositories": [
        {
            "type": "path",
            "url": "packages/Karim/SmartBackup",
            "options": {
                "symlink": true,
                "versions": {
                    "karim/smart-backup": "1.0.0"
                }
            }
        }
    ]
}
```

Then run:

```bash
composer update karim/smart-backup -W
```

Install package files:

```bash
php artisan smart-backup:install
php artisan migrate
```

---

## Releasing Version `v1.0.0`

The package version should not be hardcoded inside the package `composer.json`.

Composer package versions come from Git tags.

To release `v1.0.0`:

```bash
git add .
git commit -m "Release v1.0.0"
git tag v1.0.0
git push origin main
git push origin v1.0.0
```

Then users can install:

```bash
composer require karim/smart-backup:^1.0
```

---

## Example Package `composer.json`

Your package `composer.json` can look like this:

```json
{
    "name": "karim/smart-backup",
    "description": "A Laravel package for creating and managing database and storage backups.",
    "type": "library",
    "license": "MIT",
    "autoload": {
        "psr-4": {
            "Karim\\SmartBackup\\": "src/"
        }
    },
    "require": {
        "php": "^8.2",
        "illuminate/support": "^11.0|^12.0|^13.0",
        "spatie/laravel-backup": "^9.0|^10.0"
    },
    "extra": {
        "laravel": {
            "providers": [
                "Karim\\SmartBackup\\SmartBackupServiceProvider"
            ]
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

---

## Testing Checklist Before Publishing

Before publishing the package, test the following in a fresh Laravel application.

### 1. Composer Install

```bash
composer require karim/smart-backup:^1.0
```

For local path testing:

```bash
composer update karim/smart-backup -W
```

### 2. Publish Files

```bash
php artisan smart-backup:install
```

Or separately:

```bash
php artisan vendor:publish --tag=smart-backup-config
php artisan vendor:publish --tag=smart-backup-migrations
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Check Route Registration

Add:

```php
Route::smartBackup();
```

Then run:

```bash
php artisan route:list
```

Expected routes:

```text
backup.index
backup.store
backup.download
backup.destroy
backup.bulk-destroy
```

### 5. Create Backup

```bash
php artisan smart-backup:run
```

Or via HTTP:

```http
POST /backup
Accept: application/json
```

### 6. List Backups

```bash
php artisan smart-backup:list
```

Or via HTTP:

```http
GET /backup
Accept: application/json
```

### 7. Download Backup

```http
GET /backup/{backup}/download
```

### 8. Delete One Backup

```http
DELETE /backup/{backup}
Accept: application/json
```

### 9. Bulk Delete Backups

```http
DELETE /backup
Accept: application/json
Content-Type: application/json
```

Body:

```json
{
    "ids": [1, 2, 3]
}
```

### 10. Last Backup Protection

With this enabled:

```env
SMART_BACKUP_PREVENT_DELETE_LAST=true
```

Try deleting the final remaining backup and confirm the package prevents it.

---

## Troubleshooting

### `mysqldump was not found`

Set the dump binary folder manually:

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=C:/xampp/mysql/bin
```

Or on Linux/VPS:

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=/usr/bin
```

### Backup Fails on Shared Hosting

Check whether the hosting provider disables process functions such as:

```text
exec, shell_exec, proc_open, proc_get_status
```

Also confirm that `mysqldump` is installed and accessible.

### Backup File Not Found When Downloading

Make sure:

- the backup record has a valid `path`
- the backup file exists on the configured disk
- `SMART_BACKUP_DISK` matches the disk where the file was saved
- the file was not manually deleted

### Cannot Delete the Last Backup

This is controlled by:

```env
SMART_BACKUP_PREVENT_DELETE_LAST=true
```

To allow deleting all backups:

```env
SMART_BACKUP_PREVENT_DELETE_LAST=false
```

### Bulk Delete Returns No Matching Backups

Make sure the request body is JSON:

```json
{
    "ids": [1, 2, 3]
}
```

And headers include:

```http
Accept: application/json
Content-Type: application/json
```

---

## Security Notes

This package does not add authorization by itself.

You should protect the routes inside the host application using middleware such as:

```php
Route::middleware(['web', 'auth'])->group(function () {
    Route::smartBackup();
});
```

Or:

```php
Route::middleware(['web', 'auth:admin'])->group(function () {
    Route::smartBackup();
});
```

Do not expose backup routes publicly.

Backup archives may contain sensitive data.

---

## Limitations

- Restore is not implemented.
- No Blade views are included.
- No dashboard UI is included.
- No authorization policy is included.
- No scheduling is forced by the package.
- Full project backup is not included by default.
- Backup creation depends on Spatie Laravel Backup and server capabilities.
- MySQL/MariaDB database dumps require `mysqldump`.

---

## Scheduling Backups

The package does not force a schedule.

You can schedule the command in your host Laravel application:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('smart-backup:run')->daily();
```

Or weekly:

```php
Schedule::command('smart-backup:run')->weekly();
```

---

## License

The MIT License (MIT).
