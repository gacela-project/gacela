<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Container;

use ArrayObject;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\Exception\ContainerException;
use Gacela\Framework\Container\Exception\ContainerKeyNotFoundException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerTest extends TestCase
{
    private Container $container;

    public function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_get_non_existing_service(): void
    {
        $this->expectException(ContainerKeyNotFoundException::class);
        $this->container->get('unknown-service_name');
    }

    public function test_has_service(): void
    {
        $this->container->set('service_name', 'value');

        self::assertTrue($this->container->has('service_name'));
        self::assertFalse($this->container->has('unknown-service_name'));
    }

    public function test_remove_existing_service(): void
    {
        $this->container->set('service_name', 'value');
        $this->container->remove('service_name');

        $this->expectException(ContainerKeyNotFoundException::class);
        $this->container->get('service_name');
    }

    public function test_resolve_service_as_raw_string(): void
    {
        $this->container->set('service_name', 'value');

        $resolvedService = $this->container->get('service_name');
        self::assertSame('value', $resolvedService);

        $cachedResolvedService = $this->container->get('service_name');
        self::assertSame('value', $cachedResolvedService);
    }

    public function test_resolve_service_as_function(): void
    {
        $this->container->set('service_name', static fn () => 'value');

        $resolvedService = $this->container->get('service_name');
        self::assertSame('value', $resolvedService);

        $cachedResolvedService = $this->container->get('service_name');
        self::assertSame('value', $cachedResolvedService);
    }

    public function test_resolve_service_as_callable_class(): void
    {
        $this->container->set(
            'service_name',
            new class() {
                public function __invoke(): string
                {
                    return 'value';
                }
            }
        );

        $resolvedService = $this->container->get('service_name');
        self::assertSame('value', $resolvedService);

        $cachedResolvedService = $this->container->get('service_name');
        self::assertSame('value', $cachedResolvedService);
    }

    public function test_resolve_non_factory_service_with_random(): void
    {
        $this->container->set(
            'service_name',
            static fn () => 'value_' . random_int(0, PHP_INT_MAX)
        );

        self::assertSame(
            $this->container->get('service_name'),
            $this->container->get('service_name')
        );
    }

    public function test_resolve_factory_service_with_random(): void
    {
        $this->container->set(
            'service_name',
            $this->container->factory(
                static fn () => 'value_' . random_int(0, PHP_INT_MAX)
            )
        );

        self::assertNotSame(
            $this->container->get('service_name'),
            $this->container->get('service_name')
        );
    }

    public function test_resolve_factory_service_not_invokable(): void
    {
        $this->expectExceptionObject(ContainerException::serviceNotInvokable());

        $this->container->set(
            'service_name',
            $this->container->factory(new stdClass())
        );
    }

    public function test_extend_existing_callable_service(): void
    {
        $this->container->set('n3', 3);
        $this->container->set('service_name', static fn () => new ArrayObject([1, 2]));

        $this->container->extend(
            'service_name',
            static function (ArrayObject $arrayObject, Container $container) {
                $arrayObject->append($container->get('n3'));
                return $arrayObject;
            }
        );

        $this->container->extend(
            'service_name',
            static fn (ArrayObject $arrayObject) => $arrayObject->append(4)
        );

        /** @var ArrayObject $actual */
        $actual = $this->container->get('service_name');

        self::assertEquals(new ArrayObject([1, 2, 3, 4]), $actual);
    }

    public function test_extend_existing_object_service(): void
    {
        $this->container->set('n3', 3);
        $this->container->set('service_name', new ArrayObject([1, 2]));

        $this->container->extend(
            'service_name',
            static function (ArrayObject $arrayObject, Container $container) {
                $arrayObject->append($container->get('n3'));
                return $arrayObject;
            }
        );

        $this->container->extend(
            'service_name',
            static function (ArrayObject $arrayObject): void {
                $arrayObject->append(4);
            }
        );

        /** @var ArrayObject $actual */
        $actual = $this->container->get('service_name');

        self::assertEquals(new ArrayObject([1, 2, 3, 4]), $actual);
    }

    public function test_extend_existing_array_service(): void
    {
        $this->container->set('service_name', [1, 2]);

        $this->container->extend(
            'service_name',
            static function (array $arrayObject): array {
                $arrayObject[] = 3;
                return $arrayObject;
            }
        );

        $this->container->extend(
            'service_name',
            static function (array &$arrayObject): void {
                $arrayObject[] = 4;
            }
        );

        /** @var ArrayObject $actual */
        $actual = $this->container->get('service_name');

        self::assertEquals([1, 2, 3, 4], $actual);
    }

    public function test_extend_non_existing_service(): void
    {
        $this->container->extend('service_name', static fn () => '');

        $this->expectException(ContainerKeyNotFoundException::class);
        $this->container->get('service_name');
    }

    public function test_service_not_extendable(): void
    {
        $this->container->set('service_name', 'raw string');

        $this->expectExceptionObject(ContainerException::serviceNotExtendable());
        $this->container->extend('service_name', static fn (string $str) => $str);
    }

    public function test_extend_existing_used_object_service_is_allowed(): void
    {
        $this->container->set('service_name', new ArrayObject([1, 2]));
        $this->container->get('service_name'); // and get frozen

        $this->expectExceptionObject(ContainerException::serviceFrozen('service_name'));

        $this->container->extend(
            'service_name',
            static fn (ArrayObject $arrayObject) => $arrayObject->append(3)
        );
    }

    public function test_extend_existing_used_callable_service_then_error(): void
    {
        $this->container->set('service_name', static fn () => new ArrayObject([1, 2]));
        $this->container->get('service_name'); // and get frozen

        $this->expectExceptionObject(ContainerException::serviceFrozen('service_name'));

        $this->container->extend(
            'service_name',
            static fn (ArrayObject $arrayObject) => $arrayObject->append(3)
        );
    }

    public function test_extend_later_existing_frozen_object_service_then_error(): void
    {
        $this->container->extend(
            'service_name',
            static fn (ArrayObject $arrayObject) => $arrayObject->append(3)
        );

        $this->container->set('service_name', new ArrayObject([1, 2]));
        $this->container->get('service_name'); // and get frozen

        $this->expectExceptionObject(ContainerException::serviceFrozen('service_name'));

        $this->container->extend(
            'service_name',
            static fn (ArrayObject $arrayObject) => $arrayObject->append(4)
        );
    }

    public function test_extend_later_existing_frozen_callable_service_then_error(): void
    {
        $this->container->extend(
            'service_name',
            static fn (ArrayObject $arrayObject) => $arrayObject->append(3)
        );

        $this->container->set('service_name', static fn () => new ArrayObject([1, 2]));
        $this->container->get('service_name'); // and get frozen

        $this->expectExceptionObject(ContainerException::serviceFrozen('service_name'));

        $this->container->extend(
            'service_name',
            static fn (ArrayObject $arrayObject) => $arrayObject->append(4)
        );
    }

    public function test_set_existing_frozen_service(): void
    {
        $this->container->set('service_name', static fn () => new ArrayObject([1, 2]));
        $this->container->get('service_name'); // and get frozen

        $this->expectExceptionObject(ContainerException::serviceFrozen('service_name'));
        $this->container->set('service_name', static fn () => new ArrayObject([3]));
    }

    public function test_protect_service_is_not_resolved(): void
    {
        $service = static fn () => 'value';
        $this->container->set('service_name', $this->container->protect($service));

        self::assertSame($service, $this->container->get('service_name'));
    }

    public function test_protect_service_cannot_be_extended(): void
    {
        $this->container->set(
            'service_name',
            $this->container->protect(new ArrayObject([1, 2]))
        );

        $this->expectExceptionObject(ContainerException::serviceProtected('service_name'));

        $this->container->extend(
            'service_name',
            static fn (ArrayObject $arrayObject) => $arrayObject
        );
    }
}
