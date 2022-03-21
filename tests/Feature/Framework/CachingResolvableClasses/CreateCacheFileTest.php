<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CachingResolvableClasses;

use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function json_decode;

final class CreateCacheFileTest extends TestCase
{
    public function setUp(): void
    {
        if (is_file(self::getGacelaCacheFileName())) {
            unlink(self::getGacelaCacheFileName());
        }

        Gacela::bootstrap(__DIR__, [
            'resolvable-class-names-cache-enabled' => true,
            'mapping-interfaces' => static function (MappingInterfacesBuilder $interfacesBuilder): void {
                $interfacesBuilder->bind(StringValueInterface::class, new StringValue('testing-string'));
            },
            'suffix-types' => static function (SuffixTypesBuilder $suffixTypesBuilder): void {
                $suffixTypesBuilder
                    ->addFactory('FactoryA')
                    ->addFactory('FactoryB')
                    ->addConfig('ConfigA')
                    ->addConfig('ConfigB')
                    ->addDependencyProvider('DepProvA')
                    ->addDependencyProvider('DepProvB');
            },
        ]);
    }

    public function test_create_cache_file(): void
    {
        (new ModuleA\Facade())->loadGacelaCacheFile();
        (new ModuleB\Facade())->loadGacelaCacheFile();
        (new ModuleC\Facade())->loadGacelaCacheFile();

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
