# Smart Backup

A simple Laravel package for creating and managing database and storage backups.

Smart Backup uses `spatie/laravel-backup` internally, but adds a cleaner workflow:

- Creates database and storage backups.
- Saves backup records in the database.
- Provides Artisan commands.
- Provides optional routes for dashboard/API usage.
- Automatically detects `mysqldump` when possible.
- Saves backups in one managed folder, default: `backups`.

---

## Requirements

- PHP `^8.2`
- Laravel `^11.0|^12.0|^13.0`
- MySQL/MariaDB requires `mysqldump`

---

## Installation

```bash
composer require karim-dev/smart-backup
```

If Composer needs to update related dependencies:

```bash
composer require karim-dev/smart-backup -W
```

Publish config and migration:

```bash
php artisan smart-backup:install
php artisan migrate
```

> `smart-backup:install` already publishes the migration. Do not publish the migration again unless you need another copy.

---

## Usage

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
```

Force delete:

```bash
php artisan smart-backup:delete {id} --force
```

---

## What Is Backed Up?

By default, Smart Backup includes:

```text
storage/app
storage/demo
```

`storage/demo` is included only if it exists.

Smart Backup does **not** back up the full Laravel project.

It does not include:

```text
.env
vendor/
app/
routes/
resources/
storage/framework/
storage/logs/
```

---

## Backup Location

By default, backups are saved in:

```text
storage/app/private/backups
```

Example:

```text
storage/app/private/
└── backups/
    ├── 2026-05-09-01-00-51.zip
    └── 2026-05-09-01-01-48.zip
```

Smart Backup avoids keeping extra folders named after the project, such as:

```text
storage/app/private/zamil
storage/app/private/Laravel
```

The final backup files should be kept inside the managed `backups` folder.

---

## Configuration

The config file is published here:

```text
config/smart-backup.php
```

Most projects do **not** need to add anything to `.env`.

Optional `.env` values:

```env
SMART_BACKUP_DISK=local
SMART_BACKUP_DIRECTORY=backups
SMART_BACKUP_KEEP_BACKUPS=true
SMART_BACKUP_PREVENT_DELETE_LAST=true
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=
SMART_BACKUP_MYSQL_DUMP_TIMEOUT=300
SMART_BACKUP_MYSQL_SINGLE_TRANSACTION=true
```

---

## Optional Settings

### Change backup folder

Default:

```env
SMART_BACKUP_DIRECTORY=backups
```

Example:

```env
SMART_BACKUP_DIRECTORY=system-backups
```

---

### Keep only the latest backup

Default is to keep all backups:

```env
SMART_BACKUP_KEEP_BACKUPS=true
```

To delete old backups after a successful new backup:

```env
SMART_BACKUP_KEEP_BACKUPS=false
```

---

### Prevent deleting the last backup

Default:

```env
SMART_BACKUP_PREVENT_DELETE_LAST=true
```

---

### Custom `mysqldump` path

Normally, you do not need this.

Smart Backup tries to detect `mysqldump` automatically from common paths like XAMPP, Laragon, WAMP, `/usr/bin`, and `/usr/local/bin`.

If detection fails, set:

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=C:/xampp/mysql/bin
```

or on Linux/cPanel:

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=/usr/bin
```

You do **not** need to edit `config/database.php`.

---

## Routes

Smart Backup registers a route macro.

It does not load routes automatically.

```php
use Illuminate\Support\Facades\Route;

Route::smartBackup();
```

Default routes:

```text
GET     /backup
POST    /backup
GET     /backup/{backup}/download
DELETE  /backup/{backup}
DELETE  /backup
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

---

## Notes

Use Smart Backup command:

```bash
php artisan smart-backup:run
```

Do not use Spatie directly when testing this package:

```bash
php artisan backup:run
```

because Smart Backup adds runtime configuration, database records, managed folder behavior, and cleanup logic.

---

## Troubleshooting

### `mysqldump` is not recognized

Set the path manually:

```env
SMART_BACKUP_MYSQL_DUMP_BINARY_PATH=C:/xampp/mysql/bin
```

Then run:

```bash
php artisan optimize:clear
php artisan smart-backup:run
```

---

### `Mailer [] is not defined`

For local testing, add:

```env
MAIL_MAILER=log
```

Then run:

```bash
php artisan optimize:clear
```

---

### Duplicate migration

If you published the migration twice, delete the extra migration file before running:

```bash
php artisan migrate
```

---

## Author

**Karim Mohamed**

Package: `karim-dev/smart-backup`

GitHub: [Kareem154](https://github.com/Kareem154)

---

## License

MIT
