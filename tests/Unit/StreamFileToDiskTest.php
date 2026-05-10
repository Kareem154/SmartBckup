<?php

namespace Karim\SmartBackup\Tests\Unit;

use Illuminate\Support\Facades\Storage;
use Karim\SmartBackup\Support\StreamFileToDisk;
use Karim\SmartBackup\Tests\TestCase;

class StreamFileToDiskTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
    }

    /** @test */
    public function it_copies_a_local_file_via_stream_without_put_contents(): void
    {
        Storage::fake('local');

        $tmp = tempnam(sys_get_temp_dir(), 'sb-stream');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, 'stream-payload');

        try {
            StreamFileToDisk::copy(Storage::disk('local'), 'stored.bin', $tmp);
        } finally {
            @unlink($tmp);
        }

        $this->assertSame('stream-payload', Storage::disk('local')->get('stored.bin'));
    }
}
