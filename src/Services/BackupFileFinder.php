<?php

namespace Karim\SmartBackup\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupFileFinder
{
    /**
     * Find the latest generated backup zip file on the configured disk.
     *
     * We scan the whole disk instead of only the managed backup folder because
     * Spatie may generate the first zip inside a folder named after the host
     * application. Smart Backup then moves that zip to its managed directory.
     *
     * @return string
     */
    public function latest(): string
    {
        $disk = $this->disk();

        $files = collect($disk->allFiles())
            ->filter(fn (string $file): bool => $this->isZip($file))
            ->values();

        if ($files->isEmpty()) {
            throw new RuntimeException(
                'No backup zip files were found on the configured disk ['.
                config('smart-backup.disk', 'local').
                '].'
            );
        }

        return $files
            ->sortByDesc(fn (string $file): int => $disk->lastModified($file))
            ->first();
    }

    /**
     * Get the configured filesystem disk.
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    private function disk(): Filesystem
    {
        return Storage::disk(config('smart-backup.disk', 'local'));
    }

    /**
     * Determine whether the given file is a zip file.
     *
     * @param  string  $file
     * @return bool
     */
    private function isZip(string $file): bool
    {
        return str_ends_with(strtolower($file), '.zip');
    }
}
