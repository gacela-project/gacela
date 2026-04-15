<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins;

use Gacela\Container\Container;
use Gacela\Framework\Plugins\LazyHandlerRegistry;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class LazyHandlerRegistryTest extends TestCase
{
    public function test_get_resolves_handler_through_container(): void
    {
        $registry = new LazyHandlerRegistry(
            ['only' => stdClass::class],
            new Container(),
        );

        $handler = $registry->get('only');

        self::assertInstanceOf(stdClass::class, $handler);
    }

    public function test_get_caches_instance_between_calls(): void
    {
        $registry = new LazyHandlerRegistry(
            ['a' => stdClass::class],
            new Container(),
        );

        $first = $registry->get('a');
        $second = $registry->get('a');

        self::assertSame($first, $second);
    }

    public function test_handler_is_not_instantiated_until_requested(): void
    {
        CountingHandler::$instantiations = 0;

        $registry = new LazyHandlerRegistry(
            ['only' => CountingHandler::class],
            new Container(),
        );

        self::assertSame(0, CountingHandler::$instantiations);

        $registry->get('only');
        $registry->get('only');

        self::assertSame(1, CountingHandler::$instantiations);
    }

    public function test_has_returns_true_for_registered_key(): void
    {
        $registry = new LazyHandlerRegistry(
            ['a' => stdClass::class],
            new Container(),
        );

        self::assertTrue($registry->has('a'));
    }

    public function test_has_returns_false_for_unknown_key(): void
    {
        $registry = new LazyHandlerRegistry(
            ['a' => stdClass::class],
            new Container(),
        );

        self::assertFalse($registry->has('missing'));
    }

    public function test_keys_returns_registered_keys_in_order(): void
    {
        $registry = new LazyHandlerRegistry(
            ['a' => stdClass::class, 'b' => stdClass::class, 7 => stdClass::class],
            new Container(),
        );

        self::assertSame(['a', 'b', 7], $registry->keys());
    }

    public function test_keys_is_empty_when_no_handlers_registered(): void
    {
        $registry = new LazyHandlerRegistry([], new Container());

        self::assertSame([], $registry->keys());
    }

    public function test_get_throws_for_unknown_key(): void
    {
        $registry = new LazyHandlerRegistry(
            ['a' => stdClass::class],
            new Container(),
        );

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('No handler registered for key "missing"');

        $registry->get('missing');
    }

    public function test_get_error_lists_known_keys_when_registry_non_empty(): void
    {
        $registry = new LazyHandlerRegistry(
            ['alpha' => stdClass::class, 'beta' => stdClass::class],
            new Container(),
        );

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('alpha, beta');

        $registry->get('missing');
    }

    public function test_get_error_reports_none_when_registry_empty(): void
    {
        $registry = new LazyHandlerRegistry([], new Container());

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('(none)');

        $registry->get('missing');
    }
}

final class CountingHandler
{
    public static int $instantiations = 0;

    public function __construct()
    {
        ++self::$instantiations;
    }
}
