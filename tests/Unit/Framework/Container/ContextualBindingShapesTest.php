<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Container;

use Gacela\Container\Container as GacelaContainer;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;

/**
 * What `addContextualBinding()` accepts as an implementation.
 *
 * These used to be asserted against `ContextualBindingRegistrar`, a Gacela class
 * that narrowed the stored `mixed` before handing it to `give()` -- class
 * strings and objects through, anything else wrapped in a closure. Its only
 * remaining reason was that `give(null)` threw upstream (container#169), which
 * shipped in container 2.0, so the wrapper became a pass-through and was
 * deleted.
 *
 * The guarantees are Gacela's either way, so they are pinned here against the
 * call the registrar used to make. Deleting the class with its test would have
 * left every one of these shapes unasserted on this side.
 */
final class ContextualBindingShapesTest extends TestCase
{
    public function test_class_string_implementation_is_instantiated(): void
    {
        $container = new GacelaContainer();
        $container->when(ConsumerWithInterface::class)
            ->needs(StringValueInterface::class)
            ->give(StringValue::class);

        $consumer = $container->get(ConsumerWithInterface::class);

        self::assertInstanceOf(ConsumerWithInterface::class, $consumer);
        self::assertInstanceOf(StringValue::class, $consumer->stringValue);
    }

    public function test_object_implementation_is_injected_as_is(): void
    {
        $instance = new StringValue('preset');

        $container = new GacelaContainer();
        $container->when(ConsumerWithInterface::class)
            ->needs(StringValueInterface::class)
            ->give($instance);

        /** @var ConsumerWithInterface $consumer */
        $consumer = $container->get(ConsumerWithInterface::class);

        self::assertSame($instance, $consumer->stringValue);
    }

    public function test_callable_implementation_is_evaluated(): void
    {
        $container = new GacelaContainer();
        $container->when(ConsumerWithInterface::class)
            ->needs(StringValueInterface::class)
            ->give(static fn (): StringValue => new StringValue('from-callable'));

        /** @var ConsumerWithInterface $consumer */
        $consumer = $container->get(ConsumerWithInterface::class);

        self::assertSame('from-callable', $consumer->stringValue->value());
    }

    public function test_interface_string_implementation_is_resolved_through_the_container(): void
    {
        $container = new GacelaContainer([StringValueInterface::class => StringValue::class]);
        $container->when(ConsumerWithInterface::class)
            ->needs(StringValueInterface::class)
            ->give(StringValueInterface::class);

        /** @var ConsumerWithInterface $consumer */
        $consumer = $container->get(ConsumerWithInterface::class);

        self::assertInstanceOf(StringValue::class, $consumer->stringValue);
    }

    public function test_scalar_implementation_is_injected_as_is(): void
    {
        $container = new GacelaContainer();
        $container->when(ConsumerWithScalar::class)
            ->needs('$amount')
            ->give(42);

        /** @var ConsumerWithScalar $consumer */
        $consumer = $container->get(ConsumerWithScalar::class);

        self::assertSame(42, $consumer->amount);
    }

    /**
     * A string that is not a class name is a value, not something to resolve.
     */
    public function test_non_class_string_scalar_is_injected_verbatim(): void
    {
        $container = new GacelaContainer();
        $container->when(ConsumerWithScalarString::class)
            ->needs('$label')
            ->give('not-a-class-name');

        /** @var ConsumerWithScalarString $consumer */
        $consumer = $container->get(ConsumerWithScalarString::class);

        self::assertSame('not-a-class-name', $consumer->label);
    }

    /**
     * The case the wrapper existed for. It threw before container 2.0, so it is
     * the one shape that would tell us the dependency went backwards.
     */
    public function test_null_is_injected_rather_than_refused(): void
    {
        $container = new GacelaContainer();
        $container->when(ConsumerWithNullable::class)
            ->needs('$value')
            ->give(null);

        /** @var ConsumerWithNullable $consumer */
        $consumer = $container->get(ConsumerWithNullable::class);

        self::assertNull($consumer->value);
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

final class ConsumerWithNullable
{
    public function __construct(
        public readonly ?string $value,
    ) {
    }
}
