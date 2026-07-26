<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use ArrayObject;
use Gacela\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Extending a service whose id is a **class the container can autowire**.
 *
 * `extend()` is documented to schedule the extension when the id is not defined
 * yet. Container 1.0 broke that for class-string ids: `has()` moved to the
 * PSR-11 question ("will get() resolve this?"), which is `true` for any
 * instantiable class, so `extend()` took the already-defined branch and threw.
 * Fixed in container 1.1.
 *
 * Gacela shipped container 1.0 without noticing, because every existing
 * `extendService()` test uses a plain string id -- `'service'`,
 * `'test-service'` -- and the regression only bites when the id happens to name
 * a real class. `GacelaConfig::extendService()` accepts either.
 */
final class ExtendAutowirableClassTest extends TestCase
{
    public function test_a_service_named_after_an_autowirable_class_can_be_extended_before_it_is_defined(): void
    {
        $container = new Container();

        $container->extend(ArrayObject::class, static function (ArrayObject $object): ArrayObject {
            $object->append('extended');

            return $object;
        });

        $container->set(ArrayObject::class, static fn (): ArrayObject => new ArrayObject(['original']));

        self::assertSame(['original', 'extended'], (array)$container->get(ArrayObject::class));
    }

    public function test_extending_after_the_service_is_defined_still_works(): void
    {
        $container = new Container();

        $container->set(ArrayObject::class, static fn (): ArrayObject => new ArrayObject(['original']));

        $container->extend(ArrayObject::class, static function (ArrayObject $object): ArrayObject {
            $object->append('extended');

            return $object;
        });

        self::assertSame(['original', 'extended'], (array)$container->get(ArrayObject::class));
    }
}
