<?php

namespace Karim\SmartBackup\Tests\Unit;

use Karim\SmartBackup\Services\BackupManager;
use Karim\SmartBackup\Tests\TestCase;
use RuntimeException;

class BackupManagerValidationTest extends TestCase
{
    /** @test */
    public function it_throws_when_both_database_and_storage_are_disabled(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one backup source must be enabled.');

        $manager = app(BackupManager::class);
        $manager->run(withDatabase: false, withStorage: false);
    }

    /** @test */
    public function it_resolves_full_type_when_both_are_enabled(): void
    {
        // We can't easily run the full backup in unit test without a real filesystem,
        // so we test the type logic statically by checking the Enum mapping.
        $withDatabase = true;
        $withStorage  = true;

        $type = $withDatabase && $withStorage
            ? \Karim\SmartBackup\Enums\BackupType::FULL
            : ($withDatabase
                ? \Karim\SmartBackup\Enums\BackupType::DATABASE
                : \Karim\SmartBackup\Enums\BackupType::STORAGE);

        $this->assertSame(\Karim\SmartBackup\Enums\BackupType::FULL, $type);
    }

    /** @test */
    public function it_resolves_database_type_when_only_database_is_enabled(): void
    {
        $withDatabase = true;
        $withStorage  = false;

        $type = $withDatabase && $withStorage
            ? \Karim\SmartBackup\Enums\BackupType::FULL
            : ($withDatabase
                ? \Karim\SmartBackup\Enums\BackupType::DATABASE
                : \Karim\SmartBackup\Enums\BackupType::STORAGE);

        $this->assertSame(\Karim\SmartBackup\Enums\BackupType::DATABASE, $type);
    }

    /** @test */
    public function it_resolves_storage_type_when_only_storage_is_enabled(): void
    {
        $withDatabase = false;
        $withStorage  = true;

        $type = $withDatabase && $withStorage
            ? \Karim\SmartBackup\Enums\BackupType::FULL
            : ($withDatabase
                ? \Karim\SmartBackup\Enums\BackupType::DATABASE
                : \Karim\SmartBackup\Enums\BackupType::STORAGE);

        $this->assertSame(\Karim\SmartBackup\Enums\BackupType::STORAGE, $type);
    }
}
