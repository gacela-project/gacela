<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\CacheWritabilityCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;

final class CacheWritabilityCheckTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gacela-cache-writable-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        self::assertStringStartsWith(sys_get_temp_dir() . '/gacela-cache-writable-', $this->dir);

        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function test_a_disabled_file_cache_has_nothing_to_write(): void
    {
        $result = (new CacheWritabilityCheck(false, $this->dir))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['file cache disabled — nothing is written'], $result->details);
    }

    /**
     * A disabled cache is reported before the directory is looked at, so an
     * unwritable path nobody writes to is not an ailment.
     */
    public function test_a_disabled_file_cache_is_fine_even_where_nothing_could_be_written(): void
    {
        $result = (new CacheWritabilityCheck(false, $this->dir, static fn (): bool => false))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }

    public function test_no_resolved_directory_has_nothing_to_write(): void
    {
        $result = (new CacheWritabilityCheck(true, ''))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no cache directory resolved — nothing is written'], $result->details);
    }

    public function test_an_existing_writable_directory_passes(): void
    {
        mkdir($this->dir);

        $result = (new CacheWritabilityCheck(true, $this->dir))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['writable: ' . $this->dir], $result->details);
    }

    /**
     * The failure this exists for. Probing is injected rather than done with
     * chmod: a suite running as root can write to a mode-555 directory anyway,
     * and windows ignores the bits entirely, so the real thing would pass on
     * exactly the machines it is meant to warn.
     */
    public function test_an_existing_unwritable_directory_warns(): void
    {
        mkdir($this->dir);

        $result = (new CacheWritabilityCheck(true, $this->dir, static fn (): bool => false))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame([$this->dir . ' exists but cannot be written to'], $result->details);
        self::assertStringContainsString('every request pays the cold cost', $result->remediation);
        self::assertStringContainsString('enableFileCache()', $result->remediation);
    }

    /**
     * Missing is the normal state before anything has been cached, so it is
     * only a problem when it could not be created either.
     */
    public function test_a_missing_directory_under_a_writable_parent_passes(): void
    {
        $result = (new CacheWritabilityCheck(true, $this->dir))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['will be created on first write: ' . $this->dir], $result->details);
    }

    public function test_a_missing_directory_under_an_unwritable_parent_warns(): void
    {
        $result = (new CacheWritabilityCheck(true, $this->dir, static fn (): bool => false))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame([$this->dir . ' does not exist and cannot be created'], $result->details);
    }

    public function test_a_missing_directory_whose_parent_is_absent_warns(): void
    {
        $result = (new CacheWritabilityCheck(true, $this->dir . '/nested/deeper'))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            [$this->dir . '/nested/deeper does not exist and cannot be created'],
            $result->details,
        );
    }
}
