<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

final class AbstractProviderTest extends TestCase
{
    /**
     * `AbstractFactory` calls `provideModuleDependencies()` on a resolved
     * provider from outside the class, and the scaffolded provider template
     * declares it public, so the visibility is part of the contract rather
     * than an accident.
     *
     * A subclass that overrides the method may widen it to public on its own,
     * which hides a narrowed parent. This calls it externally on a provider
     * that does *not* override, so only the base declaration is exercised.
     */
    public function test_provide_module_dependencies_is_callable_from_outside(): void
    {
        $provider = new AbstractProviderTestBareProvider();
        $container = new Container();

        $provider->provideModuleDependencies($container);

        self::assertFalse($container->has('anything'));
    }

    public function test_register_runs_the_module_dependencies(): void
    {
        $provider = new AbstractProviderTestRecordingProvider();
        $container = new Container();

        $provider->register($container);

        self::assertSame(1, $provider->calls);
        self::assertTrue($container->has('service'));
    }
}

final class AbstractProviderTestBareProvider extends AbstractProvider
{
}

final class AbstractProviderTestRecordingProvider extends AbstractProvider
{
    public int $calls = 0;

    public function provideModuleDependencies(Container $container): void
    {
        ++$this->calls;
        $container->set('service', static fn (): string => 'value');
    }
}
