<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\DefaultGacelaClass;
use Gacela\Framework\ClassResolver\Facade\FacadeResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use PHPUnit\Framework\TestCase;

final class DefaultGacelaClassTest extends TestCase
{
    public function test_facade_type_returns_an_empty_facade(): void
    {
        self::assertInstanceOf(AbstractFacade::class, DefaultGacelaClass::forType(FacadeResolver::TYPE));
    }

    public function test_factory_type_returns_an_empty_factory(): void
    {
        self::assertInstanceOf(AbstractFactory::class, DefaultGacelaClass::forType(FactoryResolver::TYPE));
    }

    public function test_config_type_returns_an_empty_config(): void
    {
        self::assertInstanceOf(AbstractConfig::class, DefaultGacelaClass::forType(ConfigResolver::TYPE));
    }

    /**
     * A Provider has no meaningful empty implementation: an empty one would
     * register nothing and silently mask a missing Provider, so the resolver
     * reports it instead.
     */
    public function test_provider_type_has_no_default(): void
    {
        self::assertNull(DefaultGacelaClass::forType(ProviderResolver::TYPE));
    }

    public function test_unknown_type_has_no_default(): void
    {
        self::assertNull(DefaultGacelaClass::forType('SomeCustomType'));
    }

    public function test_each_call_returns_a_fresh_instance(): void
    {
        self::assertNotSame(
            DefaultGacelaClass::forType(FacadeResolver::TYPE),
            DefaultGacelaClass::forType(FacadeResolver::TYPE),
        );
    }
}
