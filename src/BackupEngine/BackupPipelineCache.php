<?php

namespace Karim\SmartBackup\BackupEngine;

final class BackupPipelineCache
{
    public static function configSnapshotKey(int $backupId): string
    {
        return 'smart-backup:pipeline:'.$backupId.':config-snapshot';
    }
}
