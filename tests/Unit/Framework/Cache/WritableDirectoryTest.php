<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\WritableDirectory;
use GacelaTest\Fixtures\ReadOnlyDirTrait;
use GacelaTest\Fixtures\WarningCollectorTrait;
use PHPUnit\Framework\TestCase;

use function chmod;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class WritableDirectoryTest extends TestCase
{
    use ReadOnlyDirTrait;
    use WarningCollectorTrait;

    protected function setUp(): void
    {
        WritableDirectory::resetCache();
    }

    protected function tearDown(): void
    {
        WritableDirectory::resetCache();
        $this->restoreReadOnlyDirs();
    }

    public function test_creates_missing_directory_and_reports_usable(): void
    {
        $dir = $this->tempPath('writable-dir-missing');

        self::assertTrue(WritableDirectory::isUsable($dir));
        self::assertDirectoryExists($dir);
    }

    public function test_existing_writable_directory_is_usable(): void
    {
        $dir = $this->tempPath('writable-dir-existing');
        mkdir($dir, 0o755, true);

        self::assertTrue(WritableDirectory::isUsable($dir));
    }

    public function test_uncreatable_directory_is_not_usable_and_emits_no_warning(): void
    {
        $dir = $this->uncreatableDir();

        $warnings = $this->collectWarnings(
            static fn (): bool => WritableDirectory::isUsable($dir),
            $usable,
        );

        self::assertFalse($usable);
        self::assertSame([], $warnings);
    }

    public function test_read_only_directory_is_not_usable(): void
    {
        $dir = $this->createReadOnlyDirOrSkip('writable-dir-readonly');

        self::assertFalse(WritableDirectory::isUsable($dir));
    }

    public function test_verdict_is_memoized_until_reset(): void
    {
        $dir = $this->createReadOnlyDirOrSkip('writable-dir-memoized');

        self::assertFalse(WritableDirectory::isUsable($dir));

        chmod($dir, 0o755);
        self::assertFalse(WritableDirectory::isUsable($dir), 'first verdict must stick until reset');

        WritableDirectory::resetCache();
        self::assertTrue(WritableDirectory::isUsable($dir));
    }

    private function tempPath(string $prefix): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-' . $prefix . '-' . uniqid('', true);
        $this->readOnlyDirs[] = $path;

        return $path;
    }
}
