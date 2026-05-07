# Smart Backup

`karim/smart-backup` is a reusable Laravel package for creating backups that contain:

- a database SQL dump
- `storage/app`
- `storage/demo`, only when that directory exists

It does not back up the full Laravel project. It does not include project root files such as `.env`, `composer.json`, `app/`, `resources/`, `routes/`, `vendor/`, `storage/framework`, or `storage/logs`.

Restore is not implemented.

## Local Installation

For version `v1.0.0`, use `^1.0` in the host application. For local path development, add the package to the host application's `composer.json`:

```json
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
```

Then run:

```bash
composer update karim/smart-backup -W
php artisan smart-backup:install
php artisan migrate
```

## Configuration

Published config file:

```bash
config/smart-backup.php
```

Supported environment variables:

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

## MySQL Dump Detection

Database backups for MySQL and MariaDB require the `mysqldump` client binary. Smart Backup automatically configures Spatie's dumper path at runtime, so host applications do not need to edit `config/database.php`.

Detection order:

- `SMART_BACKUP_MYSQL_DUMP_BINARY_PATH`
- `MYSQL_DUMP_BINARY_PATH`
- system `PATH` using `where mysqldump` on Windows or `command -v mysqldump` elsewhere
- common local/server paths such as `C:/xampp/mysql/bin`, Laragon, WAMP, MySQL/MariaDB program folders, `/usr/bin`, and `/usr/local/bin`

Set `SMART_BACKUP_MYSQL_DUMP_BINARY_PATH` to the folder that contains `mysqldump`:

```env
# Windows XAMPP
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=C:/xampp/mysql/bin
```

```env
# Linux/VPS
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=/usr/bin
```

```env
# cPanel, when your host exposes mysqldump here
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=/usr/bin
```

A full executable path such as `/usr/bin/mysqldump` or `C:/xampp/mysql/bin/mysqldump.exe` is also accepted, but the folder path is preferred.

On cPanel or VPS hosting, make sure MySQL client tools are installed and that PHP is allowed to execute processes. Disabled functions such as `exec`, `shell_exec`, `proc_open`, or `proc_get_status` can prevent backup tools from finding or running database dump binaries. If `mysqldump` is not available in `PATH`, set `SMART_BACKUP_MYSQL_DUMP_BINARY_PATH` to the directory provided by the host, commonly `/usr/bin` or `/usr/local/bin`.

## Commands

Install config and migrations:

```bash
php artisan smart-backup:install
```

Create a backup:

```bash
php artisan smart-backup:run
```

List backups:

```bash
php artisan smart-backup:list
```

Delete a backup:

```bash
php artisan smart-backup:delete {id}
php artisan smart-backup:delete {id} --force
```

Deletion respects `SMART_BACKUP_PREVENT_DELETE_LAST`.

## Routes

The package registers a route macro. It does not load routes automatically and does not force middleware, prefixes, route files, or dashboard behavior.

```php
use Illuminate\Support\Facades\Route;

Route::smartBackup();
```

Default generated routes:

```text
GET    /backup                  backup.index
POST   /backup                  backup.store
GET    /backup/{backup}/download backup.download
DELETE /backup/{backup}          backup.destroy
DELETE /backup                  backup.bulk-destroy
```

You may override the prefix, name, or controller:

```php
Route::smartBackup([
    'prefix' => 'backups',
    'name' => 'backups.',
]);
```

## Host Dashboard Integration

Dashboard integration belongs in the host Laravel application, not in this package. The package does not ship views and does not assume `auth:admin`, dashboard prefixes, Blade layouts, DataTables, or project-specific controllers.

Example host-app usage:

```php
Route::prefix('dashboard')
    ->name('dashboard.')
    ->middleware(['web', 'auth'])
    ->group(function () {
        Route::smartBackup();
    });
```

This produces route names such as:

```text
dashboard.backup.index
dashboard.backup.store
dashboard.backup.download
dashboard.backup.destroy
dashboard.backup.bulk-destroy
```

Any dashboard screens, buttons, tables, authorization rules, and middleware should be implemented by the host project.
