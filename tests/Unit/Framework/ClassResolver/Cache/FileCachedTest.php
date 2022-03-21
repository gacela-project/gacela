<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\Cache\FileCached;
use Gacela\Framework\ClassResolver\Cache\FileCachedIoInterface;
use Gacela\Framework\ClassResolver\ClassInfo;
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
            'var-dir/gacela-cache.json',
            $this->createStub(FileCachedIoInterface::class)
        );
        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $actual = $fileCached->getCachedClassName($classInfo);

        self::assertNull($actual);
    }

    public function test_existing_cached_class_name(): void
    {
        $io = $this->createStub(FileCachedIoInterface::class);
        $io->method('existsCacheFile')->willReturn(true);
        $io->method('readCacheFile')->willReturn([
            'cacheKey' => CustomClass::class,
        ]);

        $fileCached = new FileCached('var-dir/gacela-cache.json', $io);
        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $actual = $fileCached->getCachedClassName($classInfo);

        self::assertSame(CustomClass::class, $actual);
    }

    public function test_create_cache_file_with_class_name_when_file_does_not_exists(): void
    {
        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $io = $this->createMock(FileCachedIoInterface::class);
        $io->method('existsCacheFile')->willReturn(false);
        $io->expects(self::once())->method('writeCachedData')->with(
            'var-dir/gacela-cache.json',
            [
                $classInfo->getCacheKey() => CustomClass::class,
            ]
        );

        $fileCached = new FileCached('var-dir/gacela-cache.json', $io);
        $fileCached->cacheClassName($classInfo, CustomClass::class);
    }

    public function test_append_cache_class_name_when_cache_file_exists(): void
    {
        $io = $this->createMock(FileCachedIoInterface::class);
        $io->method('existsCacheFile')->willReturn(true);
        $io->method('readCacheFile')->willReturn([]);

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $fileCached = new FileCached('var-dir/gacela-cache.json', $io);
        $fileCached->cacheClassName($classInfo, CustomClass::class);

        $actual = $fileCached->getCachedClassName($classInfo);

        self::assertSame(CustomClass::class, $actual);
    }
}
