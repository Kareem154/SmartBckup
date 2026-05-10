<?php

return [

    'disk' => env('SMART_BACKUP_DISK', 'local'),

    'storage_directory' => env('SMART_BACKUP_DIRECTORY', 'backups'),

    'temporary_directory' => env('SMART_BACKUP_TEMPORARY_DIRECTORY'),

    'source_folder' => env('SMART_BACKUP_SOURCE_FOLDER'),

    'source' => [
        'paths' => array_values(array_filter([
            storage_path('app'),
            is_dir(storage_path('demo')) ? storage_path('demo') : null,
        ])),

        'exclude' => [
            storage_path('app/backups'),
            storage_path('app/private/backups'),
            storage_path('app/backup-temp'),
            storage_path('app/smart-backup-temp'),
            storage_path('framework'),
            storage_path('logs'),
            storage_path('debugbar'),
        ],
    ],

    'keep_backups' => env('SMART_BACKUP_KEEP_BACKUPS', true),

    'prevent_delete_last_backup' => env('SMART_BACKUP_PREVENT_DELETE_LAST', true),

    /*
    |--------------------------------------------------------------------------
    | Reorganize ZIP
    |--------------------------------------------------------------------------
    | When enabled, Smart Backup re-packs the generated ZIP to clean up
    | internal folder names. This reads every file in the ZIP into PHP memory,
    | so it will fail for very large backups if memory_limit is too low.
    |
    | Set to false (recommended) to skip this step and allow backups of any
    | size regardless of PHP memory_limit.
    |
    | reorganize_zip_max_bytes: if the archive is larger than this, reorganize
    | is skipped (with a warning) unless reorganize_zip_strict is true, which
    | throws instead. Set to 0 to disable the guard.
    |
    */
    'reorganize_zip' => env('SMART_BACKUP_REORGANIZE_ZIP', false),

    'reorganize_zip_max_bytes' => (int) env('SMART_BACKUP_REORGANIZE_ZIP_MAX_BYTES', 1024 * 1024 * 1024),

    'reorganize_zip_strict' => env('SMART_BACKUP_REORGANIZE_ZIP_STRICT', false),

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
