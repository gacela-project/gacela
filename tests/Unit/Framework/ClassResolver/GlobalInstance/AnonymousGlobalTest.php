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
use ReflectionProperty;

final class AnonymousGlobalTest extends TestCase
{
    /**
     * The anonymous class is not extending from Abstract[Factory,Config,AbstractProvider]
     * For this reason, the context of this anon-global will be the one of this (test)class
     * therefore it's not allowed.
     */
    public function test_error_when_non_allowed_anon_global_type(): void
    {
        $this->expectExceptionMessage("Type 'AnonymousGlobalTest' not allowed. Valid types: Config, Factory, Provider");

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

    /**
     * The normalizing fallback below can only ever find keys that start with a
     * `\`, so an entry stored verbatim is reachable through the exact-key
     * lookup alone. Injecting one is the only way to tell the two apart: every
     * key the framework itself writes is already normalized.
     */
    public function test_get_by_key_returns_an_exact_key_hit_without_normalizing_it(): void
    {
        $instance = new class() {
        };
        $cache = new ReflectionProperty(AnonymousGlobal::class, 'cachedGlobalInstances');
        /** @var array<string,object> $originalCache */
        $originalCache = $cache->getValue();

        $cache->setValue(null, ['App\Module\Facade' => $instance] + $originalCache);

        try {
            self::assertSame($instance, AnonymousGlobal::getByKey('App\Module\Facade'));
        } finally {
            $cache->setValue(null, $originalCache);
        }
    }

    public function test_get_by_key_normalizes_missing_leading_backslash(): void
    {
        AnonymousGlobal::resetCache();
        $instance = new class() {
        };
        // `overrideExistingResolvedClass` stores under the normalized key (with leading `\`).
        AnonymousGlobal::overrideExistingResolvedClass('App\\Module\\Facade', $instance);

        // Looking up WITHOUT the leading backslash must still resolve: the
        // getByKey path has a fallback that prepends `\` before looking again.
        $resolved = AnonymousGlobal::getByKey('App\\Module\\Facade');

        self::assertSame($instance, $resolved);
    }
}
