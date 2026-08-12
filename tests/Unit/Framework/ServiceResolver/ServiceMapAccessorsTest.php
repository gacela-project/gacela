<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ServiceResolver;

use Gacela\Framework\ServiceResolver\ServiceMapAccessors;
use GacelaTest\Unit\Framework\ServiceResolver\Fixtures\ChildWithoutAccessors;
use GacelaTest\Unit\Framework\ServiceResolver\Fixtures\ClassWithAccessors;
use GacelaTest\Unit\Framework\ServiceResolver\Fixtures\FirstService;
use GacelaTest\Unit\Framework\ServiceResolver\Fixtures\SecondService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class ServiceMapAccessorsTest extends TestCase
{
    public function test_an_accessor_resolves_to_the_class_the_attribute_names(): void
    {
        self::assertSame(
            FirstService::class,
            ServiceMapAccessors::classNameFor(new ReflectionClass(ClassWithAccessors::class), 'getFacade'),
        );
    }

    public function test_an_accessor_the_class_does_not_declare_resolves_to_nothing(): void
    {
        self::assertNull(
            ServiceMapAccessors::classNameFor(new ReflectionClass(ClassWithAccessors::class), 'getFactory'),
        );
    }

    public function test_a_class_declaring_nothing_resolves_to_nothing(): void
    {
        self::assertNull(ServiceMapAccessors::classNameFor(new ReflectionClass(stdClass::class), 'getFacade'));
        self::assertSame([], ServiceMapAccessors::declaredOn(new ReflectionClass(stdClass::class)));
    }

    public function test_every_declared_accessor_is_reported(): void
    {
        self::assertSame(
            ['getFacade' => FirstService::class, 'getConfig' => SecondService::class],
            ServiceMapAccessors::declaredOn(new ReflectionClass(ClassWithAccessors::class)),
        );
    }

    /**
     * Both readers have to answer a repeated accessor the same way, or the
     * analyser types the call the runtime does not make.
     */
    public function test_a_repeated_accessor_answers_the_first_declaration_in_both_readers(): void
    {
        $reflection = new ReflectionClass(ClassWithAccessors::class);

        self::assertSame(FirstService::class, ServiceMapAccessors::classNameFor($reflection, 'getFacade'));
        self::assertSame(FirstService::class, ServiceMapAccessors::declaredOn($reflection)['getFacade']);
    }

    /**
     * Attributes do not inherit, and the runtime reads the concrete class. A
     * reader that walked parents would type a call that resolves to nothing.
     */
    public function test_a_subclass_inherits_no_accessor(): void
    {
        $reflection = new ReflectionClass(ChildWithoutAccessors::class);

        self::assertNull(ServiceMapAccessors::classNameFor($reflection, 'getFacade'));
        self::assertSame([], ServiceMapAccessors::declaredOn($reflection));
    }
}
