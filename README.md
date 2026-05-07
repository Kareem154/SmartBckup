# Smart Backup

A simple Laravel package for creating and managing database and storage backups.

The package uses `spatie/laravel-backup` to create the backup file, then stores backup records in its own table.

It provides:

- create backup
- list backups
- download backup
- delete backup
- bulk delete backups
- Artisan commands
- route macro
- config and migration publishing

> This package does not include dashboard views or restore functionality.

---

## Requirements

- PHP `^8.2`
- Laravel `^11.0|^12.0|^13.0`
- `spatie/laravel-backup`
- `ZipArchive` PHP extension
- `mysqldump` for MySQL/MariaDB database backups

---

## Installation

Install the package:

```bash
composer require karim/smart-backup:^1.0
```

Publish config and migration:

```bash
php artisan smart-backup:install
```

Run migrations:

```bash
php artisan migrate
```

---

## Publish Files Manually

Publish config only:

```bash
php artisan vendor:publish --tag=smart-backup-config
```

Publish migration only:

```bash
php artisan vendor:publish --tag=smart-backup-migrations
```

Published config file:

```text
config/smart-backup.php
```

---

## Environment Variables

Add what you need to your `.env` file:

```env
SMART_BACKUP_DISK=local
SMART_BACKUP_DIRECTORY=backups
SMART_BACKUP_TEMPORARY_DIRECTORY=
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

Example for Windows XAMPP:

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=C:/xampp/mysql/bin
```

Example for Linux/VPS/cPanel:

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=/usr/bin
```

---

## What Gets Backed Up

By default, the package backs up:

- database dump
- `storage/app`
- `storage/demo` if it exists

It does not back up the full Laravel project.

Not included by default:

- `.env`
- `vendor`
- `app`
- `routes`
- `resources`
- `storage/logs`
- `storage/framework`

---

## Artisan Commands

Install package files:

```bash
php artisan smart-backup:install
```

Create backup:

```bash
php artisan smart-backup:run
```

List backups:

```bash
php artisan smart-backup:list
```

Delete backup:

```bash
php artisan smart-backup:delete {id}
```

Delete backup without confirmation:

```bash
php artisan smart-backup:delete {id} --force
```

Example:

```bash
php artisan smart-backup:delete 1 --force
```

---

## Routes

The package does not register routes automatically.

Add the route macro in your host app route file:

```php
use Illuminate\Support\Facades\Route;

Route::smartBackup();
```

Default routes:

```text
GET     /backup                    backup.index
POST    /backup                    backup.store
GET     /backup/{backup}/download  backup.download
DELETE  /backup/{backup}           backup.destroy
DELETE  /backup                    backup.bulk-destroy
```

Custom prefix and names:

```php
Route::smartBackup([
    'prefix' => 'backups',
    'name' => 'backups.',
]);
```

Dashboard example:

```php
Route::prefix('dashboard')
    ->name('dashboard.')
    ->middleware(['web', 'auth'])
    ->group(function () {
        Route::smartBackup();
    });
```

Admin dashboard example:

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

With pagination:

```http
GET /backup?per_page=20
Accept: application/json
```

### Create Backup

```http
POST /backup
Accept: application/json
```

### Download Backup

```http
GET /backup/{id}/download
```

Example:

```http
GET /backup/1/download
```

### Delete Backup

```http
DELETE /backup/{id}
Accept: application/json
```

Example:

```http
DELETE /backup/1
Accept: application/json
```

### Bulk Delete Backups

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

---

## Example Responses

Create backup success:

```json
{
    "success": true,
    "message": "تم إنشاء النسخة الاحتياطية بنجاح.",
    "result": {
        "path": "backups/example.zip",
        "name": "example.zip",
        "status": "completed",
        "message": "Backup completed successfully."
    }
}
```

Delete success:

```json
{
    "success": true,
    "message": "تم حذف النسخة الاحتياطية بنجاح."
}
```

Bulk delete success:

```json
{
    "success": true,
    "message": "تم حذف النسخ الاحتياطية المحددة بنجاح.",
    "deleted_count": 3
}
```

File not found:

```json
{
    "success": false,
    "message": "ملف النسخة الاحتياطية غير موجود."
}
```

---

## Local Path Development

For local package development before publishing:

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
php artisan smart-backup:install
php artisan migrate
```

---

## Release

Do not add the package version inside `composer.json`.

Create a Git tag instead:

```bash
git add .
git commit -m "Release v1.0.0"
git tag v1.0.0
git push origin main
git push origin v1.0.0
```

Users can then install:

```bash
composer require karim/smart-backup:^1.0
```

---

## Notes

- Restore is not implemented.
- Views are not included.
- Authorization is handled by the host application.
- Protect backup routes with middleware.
- Backup files may contain sensitive data.

---

## License

MIT
