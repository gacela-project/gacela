<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Container\ModuleAttributes;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Container\ModuleAttributes\Module\Domain\DefaultGreeting;
use GacelaTest\Feature\Container\ModuleAttributes\Module\Domain\GreetingInterface;
use GacelaTest\Feature\Container\ModuleAttributes\Module\Domain\SpecialGreeting;
use PHPUnit\Framework\TestCase;

final class ModuleAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(GreetingInterface::class, DefaultGreeting::class);
        });
    }

    public function test_singleton_attribute_returns_the_same_instance_across_resolves(): void
    {
        $factory = new Module\Factory();

        $first = $factory->createSingletonCounter();
        $second = $factory->createSingletonCounter();

        self::assertSame($first, $second);

        $first->increment();
        self::assertSame(1, $second->count);
    }

    public function test_factory_attribute_returns_fresh_instances(): void
    {
        $factory = new Module\Factory();

        self::assertNotSame(
            $factory->createFreshPrinter(),
            $factory->createFreshPrinter(),
        );
    }

    public function test_inject_attribute_overrides_the_bound_implementation(): void
    {
        $greeter = (new Module\Factory())->createGreeterWithInject();

        self::assertInstanceOf(SpecialGreeting::class, $greeter->greeting);
        self::assertSame('hello from the #[Inject] override', $greeter->greeting->greet());
    }

    public function test_binding_is_used_when_no_inject_attribute_is_present(): void
    {
        $greeter = (new Module\Factory())->createPlainGreeter();

        self::assertInstanceOf(DefaultGreeting::class, $greeter->greeting);
        self::assertSame('hello from the binding', $greeter->greeting->greet());
    }
}
