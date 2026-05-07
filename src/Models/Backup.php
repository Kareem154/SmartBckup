<?php

namespace Karim\SmartBackup\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('smart-backup.database.table', 'smart_backups');
    }
}
