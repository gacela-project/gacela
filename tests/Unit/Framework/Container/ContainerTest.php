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
        $this->container->set('service_name', static fn (): string => 'value');

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
}
