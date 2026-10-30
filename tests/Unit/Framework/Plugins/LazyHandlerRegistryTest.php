<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins;

use ArrayIterator;
use Gacela\Container\Container;
use Gacela\Framework\Exception\HandlerRegistryException;
use Gacela\Framework\Plugins\LazyHandlerRegistry;
use Iterator;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('missingHandlerKeys')]
    public function test_get_names_a_missing_handler_and_its_key(string|int $key): void
    {
        /** @var class-string $missing */
        $missing = 'App\\Export\\CsvHandlerTypo';
        $registry = new LazyHandlerRegistry([$key => $missing], new Container());

        try {
            $registry->get($key);
            self::fail('A missing handler class must be refused.');
        } catch (HandlerRegistryException $handlerRegistryException) {
            $message = $handlerRegistryException->getMessage();
            self::assertStringContainsString('"' . $missing . '" is registered under key "' . $key . '"', $message);
            self::assertStringContainsString('no such class exists', $message);
            self::assertStringContainsString('Tips:', $message);
            self::assertStringContainsString('composer dump-autoload', $message);
        }
    }

    /**
     * @return iterable<string, array{string|int}>
     */
    public static function missingHandlerKeys(): iterable
    {
        yield 'string key' => ['csv'];
        yield 'integer key' => [7];
    }

    public function test_has_and_keys_do_not_load_a_missing_handler(): void
    {
        /** @var class-string $missing */
        $missing = 'App\\Export\\CsvHandlerTypo';
        $autoloaded = [];
        $autoload = static function (string $class) use ($missing, &$autoloaded): void {
            if ($class === $missing) {
                $autoloaded[] = $class;
            }
        };
        spl_autoload_register($autoload, true, true);

        try {
            $registry = new LazyHandlerRegistry(['csv' => $missing], new Container());
            self::assertTrue($registry->has('csv'));
            self::assertSame(['csv'], $registry->keys());
            self::assertSame([], $autoloaded);
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    public function test_a_bound_interface_resolves_lazily_and_caches_its_factory_result(): void
    {
        $container = new Container();
        $built = 0;
        $container->bind(Iterator::class, static function () use (&$built): ArrayIterator {
            ++$built;
            return new ArrayIterator();
        });
        $registry = new LazyHandlerRegistry(['iterator' => Iterator::class], $container);

        self::assertTrue($registry->has('iterator'));
        self::assertSame(['iterator'], $registry->keys());
        self::assertSame(0, $built);
        $handler = $registry->get('iterator');
        self::assertInstanceOf(ArrayIterator::class, $handler);
        self::assertSame($handler, $registry->get('iterator'));
        self::assertSame(1, $built);
    }

    public function test_a_bound_name_resolves_without_attempting_autoload(): void
    {
        /** @var class-string $name */
        $name = 'App\\Export\\BoundHandler';
        $handler = new stdClass();
        $container = new Container();
        $container->set($name, $handler);

        $autoloaded = [];
        $autoload = static function (string $class) use ($name, &$autoloaded): void {
            if ($class === $name) {
                $autoloaded[] = $class;
            }
        };
        spl_autoload_register($autoload, true, true);

        try {
            $registry = new LazyHandlerRegistry(['bound' => $name], $container);
            self::assertSame($handler, $registry->get('bound'));
            self::assertSame($handler, $registry->get('bound'));
            self::assertSame([], $autoloaded);
        } finally {
            spl_autoload_unregister($autoload);
        }
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
