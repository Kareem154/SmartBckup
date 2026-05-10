<?php

namespace Karim\SmartBackup\BackupEngine;

final class BackupTypeFlags
{
    /**
     * @return array{0: bool, 1: bool} [withDatabase, withStorage]
     */
    public static function fromType(string $type): array
    {
        return match ($type) {
            'database' => [true, false],
            'storage' => [false, true],
            default => [true, true],
        };
    }
}
