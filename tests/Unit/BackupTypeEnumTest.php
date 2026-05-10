<?php

namespace Karim\SmartBackup\Tests\Unit;

use Karim\SmartBackup\Enums\BackupType;
use PHPUnit\Framework\TestCase;

class BackupTypeEnumTest extends TestCase
{
    /** @test */
    public function it_has_correct_values(): void
    {
        $this->assertSame('full', BackupType::FULL->value);
        $this->assertSame('database', BackupType::DATABASE->value);
        $this->assertSame('storage', BackupType::STORAGE->value);
    }

    /** @test */
    public function it_resolves_from_string(): void
    {
        $this->assertSame(BackupType::FULL, BackupType::from('full'));
        $this->assertSame(BackupType::DATABASE, BackupType::from('database'));
        $this->assertSame(BackupType::STORAGE, BackupType::from('storage'));
    }

    /** @test */
    public function try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(BackupType::tryFrom('invalid'));
        $this->assertNull(BackupType::tryFrom(''));
        $this->assertNull(BackupType::tryFrom('FULL'));
    }

    /** @test */
    public function try_from_falls_back_to_full_with_null_coalescing(): void
    {
        $type = BackupType::tryFrom('bad-value') ?? BackupType::FULL;

        $this->assertSame(BackupType::FULL, $type);
    }

    /** @test */
    public function all_cases_are_listed(): void
    {
        $cases = BackupType::cases();

        $this->assertCount(3, $cases);

        $values = array_map(fn($case) => $case->value, $cases);

        $this->assertContains('full', $values);
        $this->assertContains('database', $values);
        $this->assertContains('storage', $values);
    }
}
