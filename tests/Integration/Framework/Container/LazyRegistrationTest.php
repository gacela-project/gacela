<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\LocatorInterface;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;

/**
 * `Container::lazy()` defers construction without `#[Lazy]` on the class. It
 * lives on the concrete container rather than on ContainerInterface, so the
 * decorator forwards it by hand -- and the closure form is the one that can go
 * quietly wrong.
 */
final class LazyRegistrationTest extends TestCase
{
    public function test_a_lazily_bound_abstract_resolves_to_its_implementation(): void
    {
        $container = new Container();
        $container->lazy(StringValueInterface::class, StringValue::class);

        self::assertInstanceOf(StringValue::class, $container->get(StringValueInterface::class));
    }

    /**
     * The resolver invokes a lazy factory with the *inner* container, so an
     * undecorated closure would be handed something with no getLocator() and
     * fatal on the documented provider signature. Nothing else in the suite
     * covers that: every other closure path is decorated through set()/factory().
     */
    public function test_a_lazy_factory_closure_is_handed_the_decorator_not_the_inner_container(): void
    {
        $container = new Container();

        $container->lazy(StringValue::class, static function (Container $c): StringValue {
            $value = new StringValue();
            $value->setValue($c->getLocator() instanceof LocatorInterface ? 'decorated' : 'inner');

            return $value;
        });

        // Touch a property to force the proxy to run the factory; on PHP 8.3
        // there is no lazy object and it has already run.
        self::assertSame('decorated', $container->get(StringValue::class)->value());
    }

    public function test_a_lazy_class_is_not_constructed_until_it_is_used(): void
    {
        if (PHP_VERSION_ID < 80400) {
            self::markTestSkipped('Native lazy objects need PHP 8.4; on 8.3 the class is built eagerly.');
        }

        LazySpy::$constructed = 0;

        $container = new Container();
        $container->lazy(LazySpy::class);

        $instance = $container->get(LazySpy::class);

        self::assertSame(0, LazySpy::$constructed, 'resolving a lazy service must not construct it');

        // A ghost initializes on state access, not on any method call -- reading
        // the property is what makes this assert the deferral and not the clock.
        self::assertSame('built', $instance->marker);
        self::assertSame(1, LazySpy::$constructed);
    }
}

final class LazySpy
{
    public static int $constructed = 0;

    public string $marker = 'built';

    public function __construct()
    {
        ++self::$constructed;
    }
}
