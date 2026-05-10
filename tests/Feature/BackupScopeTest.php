<?php

namespace Karim\SmartBackup\Tests\Feature;

use Karim\SmartBackup\Services\BackupManager;
use Karim\SmartBackup\Tests\TestCase;
use ReflectionClass;
use Illuminate\Support\Facades\File;

class BackupScopeTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        // Skip migrations for this test
    }

    /** @test */
    public function it_configures_spatie_backup_to_use_project_base_as_relative_path()
    {
        $manager = new BackupManager();
        
        // 1. Configure specific storage paths
        $paths = [
            storage_path('app'),
            storage_path('demo'),
        ];
        config()->set('smart-backup.source.paths', $paths);
        
        // Ensure directories exist so they are not filtered out by the manager
        foreach ($paths as $path) {
            File::ensureDirectoryExists($path);
        }
        
        // 2. Access the protected configureSpatieBackup method via Reflection
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('configureSpatieBackup');
        $method->setAccessible(true);
        
        $tempDir = 'C:/temp/smart-backup-test';
        $method->invoke($manager, $tempDir, true, true);
        
        // 3. Verify the runtime configuration that Spatie Backup will use
        $spatieConfig = config('backup');
        
        $this->assertSame(
            realpath(base_path()) ?: base_path(),
            $spatieConfig['backup']['source']['files']['relative_path'],
            'relative_path must match canonical project root so Spatie Zip strips to storage/... paths.'
        );

        $this->assertEquals(
            $paths,
            $spatieConfig['backup']['source']['files']['include'],
            'Include paths must stay OS-native (do not force forward slashes on Windows).'
        );

        $this->assertNotContains(
            base_path(),
            $spatieConfig['backup']['source']['files']['include'],
            'The project root should not be included in the backup, only the specific storage paths.'
        );
    }

    /** @test */
    public function it_can_include_the_entire_storage_folder_in_the_backup()
    {
        $manager = new BackupManager();
        
        // Ensure storage directory exists
        File::ensureDirectoryExists(storage_path());
        
        // Set paths to the entire storage folder
        config()->set('smart-backup.source.paths', [storage_path()]);
        
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('configureSpatieBackup');
        $method->setAccessible(true);
        
        $method->invoke($manager, 'C:/temp/test', true, true);
        
        $spatieConfig = config('backup');
        
        $this->assertContains(
            storage_path(),
            $spatieConfig['backup']['source']['files']['include'],
            'The entire storage folder should be included in the backup include list.'
        );
    }
}
