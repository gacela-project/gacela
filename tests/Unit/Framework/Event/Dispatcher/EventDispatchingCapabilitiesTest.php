<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Dispatcher;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\ConfigReader\ReadPhpConfigEvent;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\Event\GacelaEventInterface;
use PHPUnit\Framework\TestCase;

final class EventDispatchingCapabilitiesTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetInstance();
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    public function test_no_listeners_skips_event_creation_and_dispatch(): void
    {
        $spy = $this->createSpyDispatcher(hasListeners: false);
        $this->bootstrapWithDispatcher($spy);

        $stand = $this->createDispatchingStand();
        $stand->fire();

        self::assertSame(0, $stand->createdEvents());
        self::assertSame([], $spy->dispatchedEvents());
    }

    public function test_registered_listener_creates_and_dispatches_event(): void
    {
        $spy = $this->createSpyDispatcher(hasListeners: true);
        $this->bootstrapWithDispatcher($spy);

        $stand = $this->createDispatchingStand();
        $stand->fire();

        self::assertSame(1, $stand->createdEvents());
        self::assertCount(1, $spy->dispatchedEvents());
        self::assertInstanceOf(ReadPhpConfigEvent::class, $spy->dispatchedEvents()[0]);
    }

    public function test_null_dispatcher_from_default_setup_never_dispatches(): void
    {
        Config::createWithSetup(new SetupGacela());

        $stand = $this->createDispatchingStand();
        $stand->fire();

        self::assertSame(0, $stand->createdEvents());
    }

    public function test_specific_listener_for_another_event_skips_event_creation(): void
    {
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(FakeUnrelatedEvent::class, static function (): void {});
        $this->bootstrapWithDispatcher($dispatcher);

        $stand = $this->createDispatchingStand();
        $stand->fire();

        self::assertSame(0, $stand->createdEvents());
    }

    private function bootstrapWithDispatcher(EventDispatcherInterface $dispatcher): void
    {
        Config::createWithSetup((new SetupGacela())->setEventDispatcher($dispatcher));
    }

    private function createDispatchingStand(): DispatchingStandInterface
    {
        return new class() implements DispatchingStandInterface {
            use EventDispatchingCapabilities;

            private int $createdEvents = 0;

            public function fire(): void
            {
                if (self::shouldDispatch(ReadPhpConfigEvent::class)) {
                    ++$this->createdEvents;
                    self::dispatchEvent(new ReadPhpConfigEvent('fake-path.php'));
                }
            }

            public function createdEvents(): int
            {
                return $this->createdEvents;
            }
        };
    }

    private function createSpyDispatcher(bool $hasListeners): SpyEventDispatcher
    {
        return new class($hasListeners) implements SpyEventDispatcher {
            /** @var list<object> */
            private array $dispatched = [];

            public function __construct(
                private readonly bool $hasListeners,
            ) {
            }

            public function dispatch(object $event): void
            {
                $this->dispatched[] = $event;
            }

            public function hasListeners(string $eventClass): bool
            {
                return $this->hasListeners;
            }

            public function dispatchedEvents(): array
            {
                return $this->dispatched;
            }
        };
    }
}

interface DispatchingStandInterface
{
    public function fire(): void;

    public function createdEvents(): int;
}

interface SpyEventDispatcher extends EventDispatcherInterface
{
    /**
     * @return list<object>
     */
    public function dispatchedEvents(): array;
}

final class FakeUnrelatedEvent implements GacelaEventInterface
{
    public function toString(): string
    {
        return self::class;
    }
}
