# Smart Backup

A simple Laravel package for creating and managing database and storage backups.

Smart Backup uses `spatie/laravel-backup` internally, but adds a cleaner workflow:

- Creates database and storage backups.
- Saves backup records in the database.
- Provides Artisan commands.
- Provides optional routes for dashboard/API usage.
- Supports single delete and bulk delete.
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

Create a full backup (database + storage):

```bash
php artisan smart-backup:run
```

Create a database-only backup:

```bash
php artisan smart-backup:database
```

Create a storage-only backup:

```bash
php artisan smart-backup:storage
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

It does not load routes automatically, so you can register the routes wherever you want in your Laravel application.

### Basic usage

```php
use Illuminate\Support\Facades\Route;

Route::smartBackup();
```

This will register the following routes:

```text
GET     /backup                     backup.index
POST    /backup                     backup.store
GET     /backup/{backup}/download   backup.download
DELETE  /backup/{backup}            backup.destroy
DELETE  /backup                     backup.bulk-destroy
```

You can use the route names like this:

```php
route('backup.index');
route('backup.store');
route('backup.download', $backup);
route('backup.destroy', $backup);
route('backup.bulk-destroy');
```

---

### Creating Backups via API / Dashboard

When creating a backup via the `backup.store` endpoint (POST `/backup`), the process runs as a **Queue Job** (`RunSmartBackupJob`) in the background. This ensures your web requests never timeout.

You can specify the backup type by passing it in the JSON payload.

**Full Backup:**
```json
{
    "type": "full"
}
```

**Database Only:**
```json
{
    "type": "database"
}
```

**Storage Only:**
```json
{
    "type": "storage"
}
```

The endpoint will respond instantly with:
```json
{
    "success": true,
    "queued": true,
    "message": "تم وضع النسخة الاحتياطية في الطابور بنجاح وسيتم تنفيذها في الخلفية."
}
```

> **Note:** If your `.env` is set to `QUEUE_CONNECTION=sync`, the backup will run immediately and wait to finish. If set to `database` or `redis`, you must have `php artisan queue:work` running on your server.

---

### Dashboard usage

You can register Smart Backup routes inside your dashboard route group:

```php
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')
    ->name('dashboard.')
    ->middleware(['web', 'auth'])
    ->group(function () {
        Route::smartBackup();
    });
```

This will register the following routes:

```text
GET     /dashboard/backup                     dashboard.backup.index
POST    /dashboard/backup                     dashboard.backup.store
GET     /dashboard/backup/{backup}/download   dashboard.backup.download
DELETE  /dashboard/backup/{backup}            dashboard.backup.destroy
DELETE  /dashboard/backup                     dashboard.backup.bulk-destroy
```

You can use the route names like this:

```php
route('dashboard.backup.index');
route('dashboard.backup.store');
route('dashboard.backup.download', $backup);
route('dashboard.backup.destroy', $backup);
route('dashboard.backup.bulk-destroy');
```

---

### Bulk delete

The bulk delete route is:

```text
DELETE /backup
```

If you register the routes inside a dashboard group, it becomes:

```text
DELETE /dashboard/backup
```

The route name will be:

```php
route('backup.bulk-destroy');
```

Or inside dashboard:

```php
route('dashboard.backup.bulk-destroy');
```

It expects an array of backup IDs:

```json
{
    "ids": [1, 2, 3]
}
```

Blade form example:

```blade
<form method="POST" action="{{ route('dashboard.backup.bulk-destroy') }}">
    @csrf
    @method('DELETE')

    <input type="hidden" name="ids[]" value="1">
    <input type="hidden" name="ids[]" value="2">
    <input type="hidden" name="ids[]" value="3">

    <button type="submit">Delete selected backups</button>
</form>
```

AJAX example:

```js
fetch("{{ route('dashboard.backup.bulk-destroy') }}", {
    method: "DELETE",
    headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-TOKEN": "{{ csrf_token() }}"
    },
    body: JSON.stringify({
        ids: [1, 2, 3]
    })
});
```

---

### Custom prefix and route name

You can customize the route prefix and route name:

```php
use Illuminate\Support\Facades\Route;

Route::smartBackup([
    'prefix' => 'backups',
    'name' => 'backups.',
]);
```

This will register routes like:

```text
GET     /backups                     backups.index
POST    /backups                     backups.store
GET     /backups/{backup}/download   backups.download
DELETE  /backups/{backup}            backups.destroy
DELETE  /backups                     backups.bulk-destroy
```

You can use them like this:

```php
route('backups.index');
route('backups.store');
route('backups.download', $backup);
route('backups.destroy', $backup);
route('backups.bulk-destroy');
```

---

### Custom dashboard prefix and route name

You can also combine dashboard grouping with custom Smart Backup options:

```php
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')
    ->name('dashboard.')
    ->middleware(['web', 'auth'])
    ->group(function () {
        Route::smartBackup([
            'prefix' => 'backups',
            'name' => 'backups.',
        ]);
    });
```

This will register routes like:

```text
GET     /dashboard/backups                     dashboard.backups.index
POST    /dashboard/backups                     dashboard.backups.store
GET     /dashboard/backups/{backup}/download   dashboard.backups.download
DELETE  /dashboard/backups/{backup}            dashboard.backups.destroy
DELETE  /dashboard/backups                     dashboard.backups.bulk-destroy
```

You can use them like this:

```php
route('dashboard.backups.index');
route('dashboard.backups.store');
route('dashboard.backups.download', $backup);
route('dashboard.backups.destroy', $backup);
route('dashboard.backups.bulk-destroy');
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
