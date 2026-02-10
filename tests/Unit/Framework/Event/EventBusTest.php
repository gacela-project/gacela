<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\EventBus;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class EventBusTest extends TestCase
{
    protected function setUp(): void
    {
        EventBus::resetCache();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            // Empty config, just need the event dispatcher to be available
        });
    }

    protected function tearDown(): void
    {
        EventBus::resetCache();

        $reflection = new ReflectionClass(Gacela::class);
        $method = $reflection->getMethod('resetCache');
        $method->invoke(null);
    }

    public function test_dispatch_event(): void
    {
        $event = new TestEvent('test payload');
        $called = false;

        EventBus::listen(TestEvent::class, static function (TestEvent $e) use (&$called, $event): void {
            $called = true;
            self::assertSame($event, $e);
        });

        EventBus::dispatch($event);

        self::assertTrue($called, 'Event listener should have been called');
    }

    public function test_listen_registers_specific_listener(): void
    {
        $receivedEvents = [];

        EventBus::listen(TestEvent::class, static function (TestEvent $event) use (&$receivedEvents): void {
            $receivedEvents[] = $event;
        });

        $event1 = new TestEvent('first');
        $event2 = new TestEvent('second');

        EventBus::dispatch($event1);
        EventBus::dispatch($event2);

        self::assertCount(2, $receivedEvents);
        self::assertSame('first', $receivedEvents[0]->payload);
        self::assertSame('second', $receivedEvents[1]->payload);
    }

    public function test_multiple_listeners_for_same_event(): void
    {
        $listener1Called = false;
        $listener2Called = false;

        EventBus::listen(TestEvent::class, static function () use (&$listener1Called): void {
            $listener1Called = true;
        });

        EventBus::listen(TestEvent::class, static function () use (&$listener2Called): void {
            $listener2Called = true;
        });

        EventBus::dispatch(new TestEvent('test'));

        self::assertTrue($listener1Called);
        self::assertTrue($listener2Called);
    }

    public function test_different_event_types(): void
    {
        $testEventCalled = false;
        $anotherEventCalled = false;

        EventBus::listen(TestEvent::class, static function () use (&$testEventCalled): void {
            $testEventCalled = true;
        });

        EventBus::listen(AnotherTestEvent::class, static function () use (&$anotherEventCalled): void {
            $anotherEventCalled = true;
        });

        EventBus::dispatch(new TestEvent('test'));

        self::assertTrue($testEventCalled);
        self::assertFalse($anotherEventCalled, 'AnotherTestEvent listener should not be called');

        EventBus::dispatch(new AnotherTestEvent());

        self::assertTrue($anotherEventCalled);
    }

    public function test_reset_cache_clears_dispatcher(): void
    {
        EventBus::listen(TestEvent::class, static fn () => null);

        EventBus::resetCache();

        // After reset, a new dispatcher will be fetched
        $called = false;
        EventBus::listen(TestEvent::class, static function () use (&$called): void {
            $called = true;
        });

        EventBus::dispatch(new TestEvent('test'));

        self::assertTrue($called);
    }
}

final class TestEvent
{
    public function __construct(
        public readonly string $payload,
    ) {
    }
}

final class AnotherTestEvent
{
}
