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
        
        // Assert relative_path is base_path() - this ensures "storage/app" appears as "storage/app" in the zip
        $this->assertEquals(
            str_replace('\\', '/', base_path()),
            $spatieConfig['backup']['source']['files']['relative_path'],
            'The relative path must be the project base_path to avoid absolute drive letters like C:\.'
        );
        
        // Assert include paths are correctly set and normalized
        $expectedIncludes = array_map(fn($p) => str_replace('\\', '/', $p), $paths);
        $this->assertEquals(
            $expectedIncludes,
            $spatieConfig['backup']['source']['files']['include'],
            'The include paths must match the configured storage paths.'
        );

        // Verify that the entire project root is NOT included
        $this->assertNotContains(
            str_replace('\\', '/', base_path()),
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
            str_replace('\\', '/', storage_path()),
            $spatieConfig['backup']['source']['files']['include'],
            'The entire storage folder should be included in the backup include list.'
        );
    }
}
