<?php

namespace Karim\SmartBackup\Enums;

enum BackupType: string
{
    case FULL = 'full';
    case DATABASE = 'database';
    case STORAGE = 'storage';
}
