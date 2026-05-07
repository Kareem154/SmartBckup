<?php

namespace Karim\SmartBackup\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupFileFinder
{
    public function latest(): string
    {
        $disk = $this->disk();

        $sourceFolder = $this->sourceFolder();

        if ($sourceFolder === '') {
            throw new RuntimeException('Smart Backup source folder is not configured.');
        }

        if (! $disk->exists($sourceFolder)) {
            throw new RuntimeException("Backup source folder does not exist: {$sourceFolder}");
        }

        $files = collect($disk->files($sourceFolder))
            ->filter(fn (string $file): bool => str_ends_with(strtolower($file), '.zip'));

        if ($files->isEmpty()) {
            throw new RuntimeException("No backup zip files found in source folder: {$sourceFolder}");
        }

        return $files
            ->sortByDesc(fn (string $file): int => $disk->lastModified($file))
            ->first();
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('smart-backup.disk', 'local'));
    }

    private function sourceFolder(): string
    {
        return trim((string) (
            config('smart-backup.source_folder')
            ?: config('smart-backup.storage_directory')
            ?: 'backups'
        ), '/');
    }
}
