<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CachingResolvableClasses;

use Gacela\Framework\ClassResolver\AbstractClassResolver;
use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function json_decode;

final class CreateCacheFileTest extends TestCase
{
    public function setUp(): void
    {
        if (is_file(self::getGacelaCacheFileName())) {
//            unlink(self::getGacelaCacheFileName());
        }
        Util::gacelaBootstrapWithCache(true);
    }

    public function test_create_cache_file(): void
    {
        Util::loadGacelaCacheFiles();

        $expectedJson = file_get_contents(__DIR__ . '/gacela-cache-expected.json');
        $expected = json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR);

        $actualJson = file_get_contents(self::getGacelaCacheFileName());
        $actual = json_decode($actualJson, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($expected, $actual);
    }

    private static function getGacelaCacheFileName(): string
    {
        return __DIR__ . '/' . AbstractClassResolver::GACELA_CACHE_JSON_FILE;
    }
}
