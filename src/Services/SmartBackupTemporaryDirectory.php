<?php

namespace Karim\SmartBackup\Services;

use Spatie\TemporaryDirectory\TemporaryDirectory;

class SmartBackupTemporaryDirectory extends TemporaryDirectory
{
    public function empty(): self
    {
        return $this;
    }
}
