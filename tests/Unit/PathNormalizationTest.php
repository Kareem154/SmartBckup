<?php

namespace Karim\SmartBackup\Tests\Unit;

use Karim\SmartBackup\Tests\TestCase;

class PathNormalizationTest extends TestCase
{
    /** @test */
    public function it_normalizes_windows_backslashes_to_forward_slashes(): void
    {
        $windowsPath = 'C:\\xampp\\htdocs\\laravel\\tatriz\\storage\\app';
        $normalized  = str_replace('\\', '/', $windowsPath);

        $this->assertStringNotContainsString('\\', $normalized);
        $this->assertSame('C:/xampp/htdocs/laravel/tatriz/storage/app', $normalized);
    }

    /** @test */
    public function it_leaves_forward_slashes_unchanged(): void
    {
        $unixPath   = '/var/www/html/storage/app';
        $normalized = str_replace('\\', '/', $unixPath);

        $this->assertSame('/var/www/html/storage/app', $normalized);
    }

    /** @test */
    public function it_normalizes_all_paths_in_an_array(): void
    {
        $paths = [
            'C:\\storage\\app',
            'C:\\storage\\demo',
        ];

        $normalized = array_map(fn ($p) => str_replace('\\', '/', $p), $paths);

        foreach ($normalized as $path) {
            $this->assertStringNotContainsString('\\', $path);
        }

        $this->assertSame('C:/storage/app', $normalized[0]);
        $this->assertSame('C:/storage/demo', $normalized[1]);
    }

    /** @test */
    public function it_normalizes_paths_for_exclude_list(): void
    {
        $excludePaths = [
            'C:\\storage\\app\\backups',
            'C:\\storage\\framework',
            'C:\\storage\\logs',
        ];

        $normalized = array_map(fn ($p) => str_replace('\\', '/', $p), $excludePaths);

        $this->assertSame('C:/storage/app/backups', $normalized[0]);
        $this->assertSame('C:/storage/framework', $normalized[1]);
        $this->assertSame('C:/storage/logs', $normalized[2]);
    }
}
