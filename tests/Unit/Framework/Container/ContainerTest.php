<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Container;

use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\Exception\ContainerKeyNotFoundException;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function test_get_non_existing_service(): void
    {
        $this->expectException(ContainerKeyNotFoundException::class);
        $container = new Container();
        $container->get('non-existing-service');
    }

    public function test_has_service(): void
    {
        $container = new Container();
        $container->set('existing-service', 'test');

        self::assertTrue($container->has('existing-service'));
        self::assertFalse($container->has('non-existing-service'));
    }

    public function test_get_existing_service_as_raw_string(): void
    {
        $container = new Container();
        $container->set('existing-service', 'test');

        $resolvedService = $container->get('existing-service');
        self::assertSame('test', $resolvedService);

        $cachedResolvedService = $container->get('existing-service');
        self::assertSame('test', $cachedResolvedService);
    }

    public function test_get_existing_service_as_function(): void
    {
        $container = new Container();
        $container->set('existing-service', static fn (): string => 'test');

        $resolvedService = $container->get('existing-service');
        self::assertSame('test', $resolvedService);

        $cachedResolvedService = $container->get('existing-service');
        self::assertSame('test', $cachedResolvedService);
    }
}
