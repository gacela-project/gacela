<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Dispatcher;

use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\EventDispatcherProvider;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use PHPUnit\Framework\TestCase;

final class EventDispatcherProviderTest extends TestCase
{
    protected function setUp(): void
    {
        EventDispatcherProvider::reset();
    }

    protected function tearDown(): void
    {
        EventDispatcherProvider::reset();
    }

    public function test_returns_a_null_dispatcher_before_any_resolver_is_set(): void
    {
        self::assertInstanceOf(NullEventDispatcher::class, EventDispatcherProvider::get());
    }

    public function test_memoises_the_pre_bootstrap_null_dispatcher(): void
    {
        self::assertSame(EventDispatcherProvider::get(), EventDispatcherProvider::get());
    }

    public function test_resolves_the_dispatcher_from_the_pushed_resolver(): void
    {
        $dispatcher = new ConfigurableEventDispatcher();
        EventDispatcherProvider::setResolver(static fn (): EventDispatcherInterface => $dispatcher);

        self::assertSame($dispatcher, EventDispatcherProvider::get());
    }

    public function test_resolver_is_called_lazily_only_on_first_get(): void
    {
        $calls = 0;
        EventDispatcherProvider::setResolver(static function () use (&$calls): EventDispatcherInterface {
            ++$calls;
            return new ConfigurableEventDispatcher();
        });

        self::assertSame(0, $calls, 'resolver must not run until get() is called');

        $first = EventDispatcherProvider::get();
        $second = EventDispatcherProvider::get();

        self::assertSame(1, $calls, 'resolver must run once and memoise');
        self::assertSame($first, $second);
    }

    public function test_setting_a_new_resolver_drops_the_previously_memoised_dispatcher(): void
    {
        $first = new ConfigurableEventDispatcher();
        $second = new ConfigurableEventDispatcher();

        EventDispatcherProvider::setResolver(static fn (): EventDispatcherInterface => $first);
        self::assertSame($first, EventDispatcherProvider::get());

        EventDispatcherProvider::setResolver(static fn (): EventDispatcherInterface => $second);
        self::assertSame($second, EventDispatcherProvider::get());
    }

    public function test_reset_returns_to_the_pre_bootstrap_null_dispatcher(): void
    {
        EventDispatcherProvider::setResolver(static fn (): EventDispatcherInterface => new ConfigurableEventDispatcher());
        self::assertInstanceOf(ConfigurableEventDispatcher::class, EventDispatcherProvider::get());

        EventDispatcherProvider::reset();

        self::assertInstanceOf(NullEventDispatcher::class, EventDispatcherProvider::get());
    }
}
