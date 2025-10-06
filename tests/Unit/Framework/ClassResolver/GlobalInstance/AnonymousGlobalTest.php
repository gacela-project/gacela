<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\GlobalInstance;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnonymousGlobalTest extends TestCase
{
    /**
     * The anonymous class is not extending from Abstract[Factory,Config,AbstractProvider]
     * For this reason, the context of this anon-global will be the one of this (test)class
     * therefore it's not allowed.
     */
    public function test_error_when_non_allowed_anon_global_type(): void
    {
        $this->expectExceptionMessage("Type 'AnonymousGlobalTest' not allowed");

        AnonymousGlobal::addGlobal($this, new class() {});
    }

    public function test_allowed_factory_anon_global(): void
    {
        $this->expectNotToPerformAssertions();

        AnonymousGlobal::addGlobal($this, new class() extends AbstractFactory {});
    }

    public function test_allowed_config_anon_global(): void
    {
        $this->expectNotToPerformAssertions();

        AnonymousGlobal::addGlobal($this, new class() extends AbstractConfig {});
    }

    public function test_allowed_dependency_provider_anon_global(): void
    {
        $this->expectNotToPerformAssertions();

        AnonymousGlobal::addGlobal($this, new class() extends AbstractProvider {
            public function provideModuleDependencies(Container $container): void
            {
            }
        });
    }

    #[DataProvider('providerOverrideExistingResolvedClass')]
    public function test_override_existing_resolved_class(string $className): void
    {
        $resolvedClass = new class() {};
        AnonymousGlobal::overrideExistingResolvedClass($className, $resolvedClass);

        self::assertSame($resolvedClass, AnonymousGlobal::getByClassName($className));
    }

    public static function providerOverrideExistingResolvedClass(): iterable
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
