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
