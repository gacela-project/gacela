<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\Framework\Container\Container;
use GacelaTest\Fixtures\ClassInfoTestingFacade;
use PHPUnit\Framework\TestCase;

final class ClassInfoTest extends TestCase
{
    protected function tearDown(): void
    {
        ResolvableTypes::resetToBuiltIn();
    }

    public function test_a_type_ending_in_a_pillar_suffix_keeps_its_own_key(): void
    {
        $actual = ClassInfo::from(ClassInfoTestingFacade::class, 'PriceConfig');

        self::assertSame('\GacelaTest\Fixtures\PriceConfig', $actual->getCacheKey());
    }

    public function test_a_type_ending_in_a_declared_kind_suffix_shares_the_kind_key(): void
    {
        ResolvableTypes::syncFrom(['Facade' => ['Facade'], 'Factory' => ['Factory'], 'Config' => ['Config'], 'Provider' => ['Provider'], 'Reader' => ['Reader']]);

        $actual = ClassInfo::from(ClassInfoTestingFacade::class, 'WalletReader');

        self::assertSame('\GacelaTest\Fixtures\Reader', $actual->getCacheKey());
    }

    public function test_anonymous_class(): void
    {
        $facade = new class() extends AbstractFacade {
        };
        $actual = ClassInfo::from($facade, 'Factory');

        self::assertSame('module-name@anonymous', $actual->getModuleNamespace(), 'full namespace');
        self::assertSame('module-name@anonymous\ClassInfoTest', $actual->getModuleName(), 'module');
        self::assertSame('\module-name@anonymous\ClassInfoTest\Factory', $actual->getCacheKey(), 'cache key');
    }

    public function test_anonymous_provider_class(): void
    {
        $provider = new class() extends AbstractProvider {
            public function provideModuleDependencies(Container $container): void
            {
            }
        };
        $actual = ClassInfo::from($provider, 'Provider');

        self::assertSame('module-name@anonymous', $actual->getModuleNamespace(), 'full namespace');
        self::assertSame('module-name@anonymous\ClassInfoTest', $actual->getModuleName(), 'module');
        self::assertSame('\module-name@anonymous\ClassInfoTest\Provider', $actual->getCacheKey(), 'cache key');
    }

    public function test_object_real_class(): void
    {
        $facade = new ClassInfoTestingFacade();
        $actual = ClassInfo::from($facade, 'Factory');

        self::assertSame('GacelaTest', $actual->getModuleNamespace(), 'full namespace');
        self::assertSame('Fixtures', $actual->getModuleName(), 'fixtures');
        self::assertSame('\GacelaTest\Fixtures\Factory', $actual->getCacheKey(), 'cache key');
    }

    public function test_string_real_class(): void
    {
        $actual = ClassInfo::from(ClassInfoTestingFacade::class, 'Factory');

        self::assertSame('GacelaTest', $actual->getModuleNamespace(), 'full namespace');
        self::assertSame('Fixtures', $actual->getModuleName(), 'module');
        self::assertSame('\GacelaTest\Fixtures\Factory', $actual->getCacheKey(), 'cache key');
    }

    public function test_repeated_lookups_return_the_memoized_instance(): void
    {
        $first = ClassInfo::from(ClassInfoTestingFacade::class, 'Factory');
        $second = ClassInfo::from(new ClassInfoTestingFacade(), 'Factory');

        self::assertSame($first, $second, 'the caller-class cache must hand back the very same instance');
    }

    public function test_string_class_with_leading_backslash_is_normalized(): void
    {
        $actual = ClassInfo::from('\\' . ClassInfoTestingFacade::class, 'Factory');

        self::assertSame('GacelaTest', $actual->getModuleNamespace(), 'leading backslash must be trimmed before parsing parts');
        self::assertSame('Fixtures', $actual->getModuleName());
        self::assertSame('\GacelaTest\Fixtures\Factory', $actual->getCacheKey());
    }
}
