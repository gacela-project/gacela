<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Locator;

use Gacela\Container\Exception\DependencyInvalidArgumentException;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\Locator;
use Gacela\Framework\Exception\GacelaNotBootstrappedException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * `Gacela::get()` answers from a Locator. It used to bind itself to the first
 * container it saw and keep it: a call before bootstrap pinned an empty
 * container for the rest of the process, and a second bootstrap kept serving
 * the first one.
 */
final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_get_follows_the_container_of_the_latest_bootstrap(): void
    {
        $this->bootstrapBinding(GreeterA::class);
        self::assertSame('A', Gacela::get(Greeter::class)?->hi());

        $this->bootstrapBinding(GreeterB::class);
        self::assertSame('B', Gacela::get(Greeter::class)?->hi());
        self::assertInstanceOf(GreeterB::class, Gacela::container()->get(Greeter::class));
    }

    public function test_get_before_bootstrap_says_so(): void
    {
        $this->forgetTheBootstrap();

        $this->expectException(GacelaNotBootstrappedException::class);

        Gacela::get(Greeter::class);
    }

    public function test_get_required_before_bootstrap_says_so(): void
    {
        $this->forgetTheBootstrap();

        $this->expectException(GacelaNotBootstrappedException::class);

        Gacela::getRequired(Greeter::class);
    }

    public function test_an_early_call_does_not_pin_an_empty_container(): void
    {
        $this->forgetTheBootstrap();

        try {
            Gacela::get(Greeter::class);
        } catch (GacelaNotBootstrappedException) {
        }

        $this->bootstrapBinding(GreeterA::class);

        self::assertSame('A', Gacela::get(Greeter::class)?->hi());
    }

    public function test_a_container_gets_a_locator_bound_to_itself(): void
    {
        $this->bootstrapBinding(GreeterA::class);
        Gacela::get(Greeter::class);

        $other = new Container([Greeter::class => GreeterB::class]);

        self::assertSame('B', $other->getLocator()->get(Greeter::class)?->hi());
        self::assertSame('A', Gacela::get(Greeter::class)?->hi());
    }

    /**
     * Autowiring alone would hand the plugin a new, empty container: none of
     * the bindings, and a locator that is not the one `Gacela::get()` reads.
     */
    public function test_a_plugin_asking_for_the_container_gets_the_application_one(): void
    {
        $seen = null;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$seen): void {
            $config->addBinding(Greeter::class, GreeterA::class);
            $config->addPlugin(static function (Container $container) use (&$seen): void {
                $seen = $container;
            });
        });

        self::assertSame(Gacela::container(), $seen);
    }

    public function test_every_container_parameter_of_a_plugin_gets_the_application_one(): void
    {
        $seen = [];
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$seen): void {
            $config->addPlugin(static function (Container $first, Container $second) use (&$seen): void {
                $seen = [$first, $second];
            });
        });

        self::assertSame([Gacela::container(), Gacela::container()], $seen);
    }

    public function test_a_plugin_with_an_untyped_parameter_gets_the_container_error(): void
    {
        $this->expectException(DependencyInvalidArgumentException::class);
        $this->expectExceptionMessage("No type hint found for parameter '\$label'");

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugin(static function (Container $container, $label): void {
            });
        });
    }

    /**
     * The state of a process that has not bootstrapped yet. `resetCache()`
     * keeps the main container, so it cannot stand in for that.
     */
    private function forgetTheBootstrap(): void
    {
        Gacela::resetCache();
        (new ReflectionProperty(Gacela::class, 'mainContainer'))->setValue(null, null);
    }

    /**
     * @param class-string<Greeter> $implementation
     */
    private function bootstrapBinding(string $implementation): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($implementation): void {
            $config->addBinding(Greeter::class, $implementation);
        });
    }
}
