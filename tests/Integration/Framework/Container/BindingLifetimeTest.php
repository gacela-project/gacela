<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Container\Attribute\Singleton;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * What a binding's lifetime actually is.
 *
 * `docs/container-configuration.md` used to answer "Singleton" in its quick
 * reference, and illustrated an alias with "both resolve to the same instance".
 * Neither holds for the two shapes that construct something: a binding is
 * wiring, not an instance, and the container builds it per resolution. Its own
 * prose says as much -- "an unregistered class is autowired fresh on each
 * `get()`, `#[Singleton]` is the opt-in for caching" -- so the table contradicted
 * the page it was on.
 *
 * Pinned here because it is a lifetime decision a reader makes from that table:
 * a repository holding a lazily built cache is rebuilt on every call.
 */
final class BindingLifetimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
    }

    public function test_a_class_string_binding_is_built_on_every_resolution(): void
    {
        $container = $this->containerWith(static function (GacelaConfig $config): void {
            $config->addBinding(BindingLifetimeContract::class, BindingLifetimeImplementation::class);
        });

        self::assertNotSame(
            $container->get(BindingLifetimeContract::class),
            $container->get(BindingLifetimeContract::class),
        );
    }

    public function test_a_closure_binding_runs_the_closure_on_every_resolution(): void
    {
        $built = 0;

        $container = $this->containerWith(static function (GacelaConfig $config) use (&$built): void {
            $config->addBinding('built.per.call', static function () use (&$built): stdClass {
                ++$built;

                return new stdClass();
            });
        });

        $container->get('built.per.call');
        $container->get('built.per.call');

        self::assertSame(2, $built);
    }

    /**
     * An already-constructed object is the one shape that is shared, and only
     * because there is nothing left to build.
     */
    public function test_binding_an_object_hands_that_object_back(): void
    {
        $instance = new stdClass();

        $container = $this->containerWith(static function (GacelaConfig $config) use ($instance): void {
            $config->addBinding('the.instance', $instance);
        });

        self::assertSame($instance, $container->get('the.instance'));
        self::assertSame($instance, $container->get('the.instance'));
    }

    /**
     * The alias reaches the same *service* -- same class, same wiring -- which is
     * not the same as the same object.
     */
    public function test_an_alias_resolves_the_same_service_but_not_the_same_instance(): void
    {
        $container = $this->containerWith(static function (GacelaConfig $config): void {
            $config->addBinding(BindingLifetimeContract::class, BindingLifetimeImplementation::class);
            $config->addAlias('the.alias', BindingLifetimeContract::class);
        });

        $viaAlias = $container->get('the.alias');

        self::assertInstanceOf(BindingLifetimeImplementation::class, $viaAlias);
        self::assertNotSame($container->get(BindingLifetimeContract::class), $viaAlias);
    }

    /**
     * The documented opt-in, so the correction has something to point at.
     */
    public function test_a_singleton_attribute_is_what_caches_an_instance(): void
    {
        $container = $this->containerWith(static function (GacelaConfig $config): void {
        });

        self::assertSame(
            $container->get(BindingLifetimeCachedService::class),
            $container->get(BindingLifetimeCachedService::class),
        );
    }

    private function containerWith(callable $configFn): Container
    {
        Gacela::bootstrap(__DIR__, $configFn(...));

        return Container::withConfig(Config::getInstance());
    }
}

interface BindingLifetimeContract
{
}

final class BindingLifetimeImplementation implements BindingLifetimeContract
{
}

#[Singleton]
final class BindingLifetimeCachedService
{
}
