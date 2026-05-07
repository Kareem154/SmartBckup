<?php

namespace Karim\SmartBackup\Services;

use Karim\SmartBackup\Models\Backup;

class BackupRunResult
{
    public function __construct(
        public readonly Backup $backup,
        public readonly string $path,
        public readonly string $name,
        public readonly string $status,
        public readonly string $message,
    ) {}

    public static function fromBackup(Backup $backup, string $message = 'Backup completed successfully.'): self
    {
        return new self(
            backup: $backup,
            path: (string) $backup->path,
            name: (string) $backup->name,
            status: (string) $backup->status,
            message: $message,
        );
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'name' => $this->name,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
