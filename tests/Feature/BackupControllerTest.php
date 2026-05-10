<?php

namespace Karim\SmartBackup\Tests\Feature;

use Illuminate\Http\Request;
use Karim\SmartBackup\Enums\BackupType;
use Karim\SmartBackup\Jobs\RunSmartBackupJob;
use Karim\SmartBackup\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class BackupControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function store_dispatches_full_backup_job_by_default(): void
    {
        $this->post(route('backup.store'))
            ->assertJson([
                'success' => true,
                'queued'  => true,
            ]);

        Queue::assertPushed(RunSmartBackupJob::class, function ($job) {
            return $job->type === BackupType::FULL;
        });
    }

    /** @test */
    public function store_dispatches_database_backup_job_when_type_is_database(): void
    {
        $this->postJson(route('backup.store'), ['type' => 'database'])
            ->assertJson([
                'success' => true,
                'queued'  => true,
            ]);

        Queue::assertPushed(RunSmartBackupJob::class, function ($job) {
            return $job->type === BackupType::DATABASE;
        });
    }

    /** @test */
    public function store_dispatches_storage_backup_job_when_type_is_storage(): void
    {
        $this->postJson(route('backup.store'), ['type' => 'storage'])
            ->assertJson([
                'success' => true,
                'queued'  => true,
            ]);

        Queue::assertPushed(RunSmartBackupJob::class, function ($job) {
            return $job->type === BackupType::STORAGE;
        });
    }

    /** @test */
    public function store_returns_422_for_invalid_type(): void
    {
        $this->postJson(route('backup.store'), ['type' => 'invalid-type'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /** @test */
    public function store_accepts_null_type_and_defaults_to_full(): void
    {
        $this->postJson(route('backup.store'), ['type' => null])
            ->assertJson([
                'success' => true,
                'queued'  => true,
            ]);

        Queue::assertPushed(RunSmartBackupJob::class, function ($job) {
            return $job->type === BackupType::FULL;
        });
    }
}
