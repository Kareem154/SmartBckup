<?php

namespace Karim\SmartBackup\Tests\Unit;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Karim\SmartBackup\Services\BackupFileOrganizer;
use Karim\SmartBackup\Tests\TestCase;
use RuntimeException;

class BackupFileOrganizerGuardTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
    }

    /** @test */
    public function it_skips_reorganize_and_logs_when_archive_exceeds_max_bytes(): void
    {
        Log::spy();
        Storage::fake('local');
        Storage::disk('local')->put('backups/test.zip', str_repeat('a', 500));

        config([
            'smart-backup.disk' => 'local',
            'smart-backup.reorganize_zip' => true,
            'smart-backup.reorganize_zip_max_bytes' => 100,
            'smart-backup.reorganize_zip_strict' => false,
        ]);

        $result = (new BackupFileOrganizer)->reorganize('backups/test.zip');

        $this->assertSame('backups/test.zip', $result);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Smart Backup skipped reorganize_zip')
                && isset($context['archive_bytes'], $context['max_bytes'])
                && $context['archive_bytes'] > $context['max_bytes']);
    }

    /** @test */
    public function it_throws_when_strict_and_archive_exceeds_max_bytes(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/test.zip', str_repeat('b', 500));

        config([
            'smart-backup.disk' => 'local',
            'smart-backup.reorganize_zip' => true,
            'smart-backup.reorganize_zip_max_bytes' => 100,
            'smart-backup.reorganize_zip_strict' => true,
        ]);

        $this->expectException(RuntimeException::class);

        (new BackupFileOrganizer)->reorganize('backups/test.zip');
    }
}
