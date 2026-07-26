<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Container\ModuleAttributes;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Container\ModuleAttributes\Module\Domain\DefaultGreeting;
use GacelaTest\Feature\Container\ModuleAttributes\Module\Domain\GreetingInterface;
use GacelaTest\Feature\Container\ModuleAttributes\Module\Domain\SpecialGreeting;
use PHPUnit\Framework\TestCase;

final class FactoryMakeTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(GreetingInterface::class, DefaultGreeting::class);
        });
    }

    public function test_make_autowires_a_plain_typed_dependency(): void
    {
        $greeter = (new Module\Factory())->makePlainGreeter();

        self::assertInstanceOf(DefaultGreeting::class, $greeter->greeting);
        self::assertSame('hello from the binding', $greeter->greeting->greet());
    }

    public function test_make_returns_the_same_instance_for_a_singleton_attribute(): void
    {
        $factory = new Module\Factory();

        $first = $factory->makeSingletonCounter();
        $second = $factory->makeSingletonCounter();

        self::assertSame($first, $second);

        $first->increment();
        self::assertSame(1, $second->count);
    }

    public function test_make_returns_fresh_instances_for_a_factory_attribute(): void
    {
        $factory = new Module\Factory();

        self::assertNotSame(
            $factory->makeFreshPrinter(),
            $factory->makeFreshPrinter(),
        );
    }

    public function test_make_honors_the_inject_attribute_override(): void
    {
        $greeter = (new Module\Factory())->makeGreeterWithInject();

        self::assertInstanceOf(SpecialGreeting::class, $greeter->greeting);
        self::assertSame('hello from the #[Inject] override', $greeter->greeting->greet());
    }

    public function test_make_applies_runtime_parameter_overrides(): void
    {
        $override = new SpecialGreeting();

        $greeter = (new Module\Factory())->makePlainGreeterWith($override);

        self::assertSame($override, $greeter->greeting);
    }
}
