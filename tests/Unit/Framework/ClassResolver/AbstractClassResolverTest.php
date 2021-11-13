<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

final class AbstractClassResolverTest extends TestCase
{
    /**
     * The anonymous class is not extending from Abstract[Factory,Config,DependencyProvider]
     * For this reason, the context of this anon-global will be the one of this (test)class
     * therefore it's not allowed.
     */
    public function test_error_when_non_allowed_anon_global_type(): void
    {
        $this->expectErrorMessage("Type 'AbstractClassResolverTest' not allowed");

        AbstractClassResolver::addAnonymousGlobal($this, new class () {
        });
    }

    public function test_allowed_factory_anon_global(): void
    {
        AbstractClassResolver::addAnonymousGlobal($this, new class () extends AbstractFactory {
        });

        self::assertTrue(true); # Assert non error is thrown
    }

    public function test_allowed_config_anon_global(): void
    {
        AbstractClassResolver::addAnonymousGlobal($this, new class () extends AbstractConfig {
        });

        self::assertTrue(true); # Assert non error is thrown
    }

    public function test_allowed_dependency_provider_anon_global(): void
    {
        AbstractClassResolver::addAnonymousGlobal($this, new class () extends AbstractDependencyProvider {
            public function provideModuleDependencies(Container $container): void
            {
            }
        });

        self::assertTrue(true); # Assert non error is thrown
    }

    /**
     * @dataProvider providerOverrideExistingResolvedClass
     */
    public function test_override_existing_resolved_class(string $className): void
    {
        $resolvedClass = new class () {
        };
        AbstractClassResolver::overrideExistingResolvedClass($className, $resolvedClass);

        self::assertSame($resolvedClass, AbstractClassResolver::getCachedGlobalInstance($className));
    }

    public function providerOverrideExistingResolvedClass(): iterable
    {
        yield 'using the module prefix' => [
            'App\Module\ModuleClassNameFacade',
        ];

        yield 'not using the module prefix in the class' => [
            'App\Module\ClassNameFacade',
        ];

        yield 'starting with \ and using the module prefix' => [
            '\App\Module\ModuleClassNameFacade',
        ];

        yield 'starting with \ and not using the module prefix in the class' => [
            '\App\Module\ClassNameFacade',
        ];
    }
}
