<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\Cache\FileCached;
use Gacela\Framework\ClassResolver\Cache\FileCachedIoInterface;
use GacelaTest\Fixtures\CustomClass;
use PHPUnit\Framework\TestCase;

final class FileCachedTest extends TestCase
{
    public function setUp(): void
    {
        FileCached::cleanCache();
    }

    public function test_non_existing_cached_class_name(): void
    {
        $fileCached = new FileCached(
            'gacela-cache.php',
            $this->createStub(FileCachedIoInterface::class)
        );
        $actual = $fileCached->getCachedClassName('cacheKey');

        self::assertNull($actual);
    }

    public function test_existing_cached_class_name(): void
    {
        $io = $this->createStub(FileCachedIoInterface::class);
        $io->method('existsCacheFile')->willReturn(true);
        $io->method('readCacheFile')->willReturn([
            'cacheKey' => CustomClass::class,
        ]);

        $fileCached = new FileCached('gacela-cache.php', $io);
        $actual = $fileCached->getCachedClassName('cacheKey');

        self::assertSame(CustomClass::class, $actual);
    }

    public function test_create_cache_file_with_class_name_when_file_does_not_exists(): void
    {
        $io = $this->createMock(FileCachedIoInterface::class);
        $io->method('existsCacheFile')->willReturn(false);
        $io->expects(self::once())->method('writeCachedData')->with(
            'gacela-cache.php',
            [
                'cacheKey' => CustomClass::class,
            ]
        );

        $fileCached = new FileCached('gacela-cache.php', $io);
        $fileCached->cacheClassName('cacheKey', CustomClass::class);
    }

    public function test_append_cache_class_name_when_cache_file_exists(): void
    {
        $io = $this->createMock(FileCachedIoInterface::class);
        $io->method('existsCacheFile')->willReturn(true);
        $io->method('readCacheFile')->willReturn([]);

        $fileCached = new FileCached('gacela-cache.php', $io);
        $fileCached->cacheClassName('cacheKey', CustomClass::class);

        $actual = $fileCached->getCachedClassName('cacheKey');

        self::assertSame(CustomClass::class, $actual);
    }
}
