<?php

namespace Karim\SmartBackup\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * Stream a file from the real filesystem into a Laravel storage disk.
 * Prefer this over Storage::put($path, file_get_contents($file)) for large files.
 */
final class StreamFileToDisk
{
    public static function copy(Filesystem $disk, string $destinationPath, string $absoluteLocalPath): void
    {
        $stream = @fopen($absoluteLocalPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open file for streaming: {$absoluteLocalPath}");
        }

        try {
            $disk->writeStream($destinationPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
