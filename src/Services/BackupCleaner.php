<?php

namespace Karim\SmartBackup\Services;

use Illuminate\Support\Facades\Storage;
use Karim\SmartBackup\Models\Backup;
use RuntimeException;

class BackupCleaner
{
    public function delete(Backup $backup): void
    {
        if ($this->shouldPreventDeletingLastBackup() && Backup::query()->count() <= 1) {
            throw new RuntimeException('Cannot delete the last backup.');
        }

        $this->deleteFile($backup);

        $backup->delete();
    }

    public function bulkDelete(array $ids): int
    {
        $ids = $this->validIds($ids);

        if ($ids === []) {
            return 0;
        }

        $backups = Backup::query()
            ->whereKey($ids)
            ->get();

        if ($backups->isEmpty()) {
            return 0;
        }

        if ($this->shouldPreventDeletingLastBackup() && Backup::query()->count() - $backups->count() <= 0) {
            throw new RuntimeException('Cannot delete the last backup.');
        }

        $deletedCount = 0;

        foreach ($backups as $backup) {
            $this->deleteFile($backup);
            $backup->delete();
            $deletedCount++;
        }

        return $deletedCount;
    }

    public function deleteOldExcept(Backup $currentBackup): int
    {
        $backups = Backup::query()
            ->whereKeyNot($currentBackup->getKey())
            ->get();

        $deletedCount = 0;

        foreach ($backups as $backup) {
            $this->deleteFile($backup);
            $backup->delete();
            $deletedCount++;
        }

        return $deletedCount;
    }

    private function deleteFile(Backup $backup): void
    {
        if (! $backup->path) {
            return;
        }

        $disk = Storage::disk($backup->disk ?: config('smart-backup.disk', 'local'));

        if ($disk->exists($backup->path)) {
            $disk->delete($backup->path);
        }
    }

    private function shouldPreventDeletingLastBackup(): bool
    {
        return filter_var(config('smart-backup.prevent_delete_last_backup', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function validIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
