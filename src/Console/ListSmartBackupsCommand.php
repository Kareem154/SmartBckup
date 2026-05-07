<?php

namespace Karim\SmartBackup\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Karim\SmartBackup\Models\Backup;

class ListSmartBackupsCommand extends Command
{
    protected $signature = 'smart-backup:list';

    protected $description = 'List Smart Backup records.';

    public function handle(): int
    {
        $backups = Backup::query()
            ->latest()
            ->get();

        if ($backups->isEmpty()) {
            $this->info('No backups found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Status', 'Disk', 'Size', 'File Exists', 'Created At'],
            $backups->map(fn (Backup $backup): array => [
                $backup->id,
                $backup->name,
                $backup->status,
                $backup->disk,
                $this->formatSize($backup->size),
                $this->fileExists($backup) ? 'Yes' : 'No',
                $backup->created_at?->format('Y-m-d H:i:s'),
            ])->all()
        );

        return self::SUCCESS;
    }

    private function fileExists(Backup $backup): bool
    {
        if (! $backup->path) {
            return false;
        }

        return Storage::disk($backup->disk)->exists($backup->path);
    }

    private function formatSize(?int $bytes): string
    {
        if ($bytes === null) {
            return 'unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, $unitIndex === 0 ? 0 : 2).' '.$units[$unitIndex];
    }
}
