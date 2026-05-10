<?php

namespace Karim\SmartBackup\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;
use Throwable;

class BackupFileOrganizer
{
    public function reorganize(string $backupPath): string
    {
        if (! filter_var(config('smart-backup.reorganize_zip', false), FILTER_VALIDATE_BOOLEAN)) {
            return $backupPath;
        }

        $disk = Storage::disk(config('smart-backup.disk', 'local'));

        if (! $disk->exists($backupPath)) {
            throw new RuntimeException("Backup file does not exist: {$backupPath}");
        }

        try {
            $archiveBytes = $disk->size($backupPath);
        } catch (Throwable) {
            $archiveBytes = null;
        }

        $maxBytes = (int) config('smart-backup.reorganize_zip_max_bytes', 1024 * 1024 * 1024);

        if ($archiveBytes !== null && $maxBytes > 0 && $archiveBytes > $maxBytes) {
            if (filter_var(config('smart-backup.reorganize_zip_strict', false), FILTER_VALIDATE_BOOLEAN)) {
                throw new RuntimeException(
                    'Smart Backup: reorganize_zip is enabled but this archive ('.(string) $archiveBytes.' bytes) exceeds '.
                    'reorganize_zip_max_bytes ('.(string) $maxBytes.'). Keep reorganize_zip disabled for large backups '.
                    'or use server-level backups.'
                );
            }

            Log::warning('Smart Backup skipped reorganize_zip: archive exceeds reorganize_zip_max_bytes.', [
                'backup_path' => $backupPath,
                'archive_bytes' => $archiveBytes,
                'max_bytes' => $maxBytes,
            ]);

            return $backupPath;
        }

        try {
            $fullPath = $disk->path($backupPath);
        } catch (Throwable) {
            return $backupPath;
        }

        $tempPath = $fullPath.'.temp';

        $zip = new ZipArchive;
        $newZip = new ZipArchive;

        if ($zip->open($fullPath) !== true) {
            throw new RuntimeException("Unable to open backup zip: {$backupPath}");
        }

        if ($newZip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $zip->close();

            throw new RuntimeException("Unable to create temporary backup zip: {$tempPath}");
        }

        $projectFolder = $this->projectFolderName($backupPath);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            if ($filename === false) {
                continue;
            }

            $content = $zip->getFromIndex($i);

            if ($content === false) {
                continue;
            }

            $newFilename = str_starts_with($filename, 'db-dumps/')
                ? $filename
                : $projectFolder.'/'.ltrim($filename, '/');

            $newZip->addFromString($newFilename, $content);
        }

        $zip->close();
        $newZip->close();

        @unlink($fullPath);

        if (! @rename($tempPath, $fullPath)) {
            throw new RuntimeException("Unable to replace original backup zip: {$backupPath}");
        }

        return $backupPath;
    }

    private function projectFolderName(string $backupPath): string
    {
        $prefix = trim((string) config('smart-backup.project_folder_prefix', 'backup-project'), '-');

        $filename = pathinfo($backupPath, PATHINFO_FILENAME);

        return "{$prefix}-{$filename}";
    }
}
