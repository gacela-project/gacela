<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Container;

use Gacela\Container\Container as GacelaContainer;
use Gacela\Framework\Container\ContextualBindingRegistrar;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;

final class ContextualBindingRegistrarTest extends TestCase
{
    public function test_class_string_implementation_is_instantiated(): void
    {
        $container = new GacelaContainer();
        ContextualBindingRegistrar::register(
            $container,
            ConsumerWithInterface::class,
            StringValueInterface::class,
            StringValue::class,
        );

        $consumer = $container->get(ConsumerWithInterface::class);

        self::assertInstanceOf(ConsumerWithInterface::class, $consumer);
        self::assertInstanceOf(StringValue::class, $consumer->stringValue);
    }

    public function test_object_implementation_is_injected_as_is(): void
    {
        $instance = new StringValue('preset');

        $container = new GacelaContainer();
        ContextualBindingRegistrar::register(
            $container,
            ConsumerWithInterface::class,
            StringValueInterface::class,
            $instance,
        );

        /** @var ConsumerWithInterface $consumer */
        $consumer = $container->get(ConsumerWithInterface::class);

        self::assertSame($instance, $consumer->stringValue);
    }

    public function test_callable_implementation_is_evaluated(): void
    {
        $container = new GacelaContainer();
        ContextualBindingRegistrar::register(
            $container,
            ConsumerWithInterface::class,
            StringValueInterface::class,
            static fn (): StringValue => new StringValue('from-callable'),
        );

        /** @var ConsumerWithInterface $consumer */
        $consumer = $container->get(ConsumerWithInterface::class);

        self::assertSame('from-callable', $consumer->stringValue->value());
    }

    public function test_interface_string_implementation_is_resolved_through_the_container(): void
    {
        $container = new GacelaContainer([StringValueInterface::class => StringValue::class]);
        ContextualBindingRegistrar::register(
            $container,
            ConsumerWithInterface::class,
            StringValueInterface::class,
            StringValueInterface::class,
        );

        /** @var ConsumerWithInterface $consumer */
        $consumer = $container->get(ConsumerWithInterface::class);

        self::assertInstanceOf(StringValue::class, $consumer->stringValue);
    }

    public function test_scalar_implementation_is_injected_as_is(): void
    {
        $container = new GacelaContainer();
        ContextualBindingRegistrar::register(
            $container,
            ConsumerWithScalar::class,
            '$amount',
            42,
        );

        /** @var ConsumerWithScalar $consumer */
        $consumer = $container->get(ConsumerWithScalar::class);

        self::assertSame(42, $consumer->amount);
    }

    public function test_non_class_string_scalar_is_injected_verbatim(): void
    {
        $container = new GacelaContainer();
        ContextualBindingRegistrar::register(
            $container,
            ConsumerWithScalarString::class,
            '$label',
            'not-a-class-name',
        );

        /** @var ConsumerWithScalarString $consumer */
        $consumer = $container->get(ConsumerWithScalarString::class);

        self::assertSame('not-a-class-name', $consumer->label);
    }
}

final class ConsumerWithInterface
{
    public function __construct(
        public readonly StringValueInterface $stringValue,
    ) {
    }
}

final class ConsumerWithScalar
{
    public function __construct(
        public readonly int $amount,
    ) {
    }
}

final class ConsumerWithScalarString
{
    public function __construct(
        public readonly string $label,
    ) {
    }
}
