<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CachingResolvableClasses;

use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function json_decode;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        if (is_file($this->getGacelaCacheFileName())) {
            unlink($this->getGacelaCacheFileName());
        }

        Gacela::bootstrap(__DIR__);
    }

    public function test_create_cache_file(): void
    {
        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();
        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();

        (new ModuleB\FacadeModuleB())->loadGacelaCacheFile();
        (new ModuleB\FacadeModuleB())->loadGacelaCacheFile();

        (new ModuleC\Facade())->loadGacelaCacheFile();
        (new ModuleC\Facade())->loadGacelaCacheFile();

        $expectedJson = file_get_contents(__DIR__ . '/gacela-cache-expected.json');
        $expected = json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR);

        $actualJson = file_get_contents($this->getGacelaCacheFileName());
        $actual = json_decode($actualJson, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($expected, $actual);
    }

    private function getGacelaCacheFileName(): string
    {
        return __DIR__ . '/' . AbstractClassResolver::GACELA_CACHE_JSON_FILE;
    }
}
