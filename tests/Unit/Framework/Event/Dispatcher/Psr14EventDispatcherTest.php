<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Dispatcher;

use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use Gacela\Framework\Event\Dispatcher\Psr14EventDispatcher;
use Gacela\Framework\Event\GacelaEventInterface;
use GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory\FakeEvent;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

final class Psr14EventDispatcherTest extends TestCase
{
    public function test_it_is_a_psr14_dispatcher(): void
    {
        $dispatcher = new Psr14EventDispatcher(new NullEventDispatcher());

        self::assertInstanceOf(PsrEventDispatcherInterface::class, $dispatcher);
    }

    public function test_the_listeners_of_the_wrapped_dispatcher_run(): void
    {
        $received = [];

        $configured = new ConfigurableEventDispatcher();
        $configured->registerSpecificListener(
            FakeEvent::class,
            static function (FakeEvent $event) use (&$received): void {
                $received[] = $event;
            },
        );

        $event = new FakeEvent();
        (new Psr14EventDispatcher($configured))->dispatch($event);

        self::assertSame([$event], $received);
    }

    /**
     * PSR-14: "returns the Event that was passed". Gacela's own interface
     * returns nothing, which is why this class exists rather than the interface
     * being widened -- a caller holding a `Psr\EventDispatcher\EventDispatcherInterface`
     * writes `$event = $dispatcher->dispatch($event)` and must get its event.
     */
    public function test_it_returns_the_event_it_was_given(): void
    {
        $event = new FakeEvent();

        $returned = (new Psr14EventDispatcher(new NullEventDispatcher()))->dispatch($event);

        self::assertSame($event, $returned);
    }

    /**
     * The guard `docs/events.md` promises: a dispatcher that declines a class
     * in `hasListeners()` is not told about it. Gacela's dispatch sites ask
     * before allocating, and a library dispatching through this adapter gets
     * the same answer honoured.
     */
    public function test_a_dispatcher_that_wants_nothing_is_not_told(): void
    {
        $refusing = new RefusingDispatcher();

        $returned = (new Psr14EventDispatcher($refusing))->dispatch(new FakeEvent());

        self::assertSame([], $refusing->received());
        self::assertInstanceOf(FakeEvent::class, $returned, 'the event is still returned');
    }

    /**
     * PSR-14 requires a dispatcher to return immediately when the event it is
     * handed is already stopped. Gacela's dispatcher is notify-only and does
     * not stop between its own listeners; refusing an already-stopped event is
     * the part that costs nothing and is simply correct.
     */
    public function test_an_already_stopped_event_is_not_dispatched(): void
    {
        $received = [];

        $configured = new ConfigurableEventDispatcher();
        $configured->registerGenericListeners([static function (object $event) use (&$received): void {
            $received[] = $event;
        }]);

        $event = new StoppedEvent();
        $returned = (new Psr14EventDispatcher($configured))->dispatch($event);

        self::assertSame([], $received);
        self::assertSame($event, $returned);
    }

    public function test_an_unstopped_stoppable_event_is_dispatched(): void
    {
        $received = [];

        $configured = new ConfigurableEventDispatcher();
        $configured->registerGenericListeners([static function (object $event) use (&$received): void {
            $received[] = $event;
        }]);

        $event = new UnstoppedEvent();
        (new Psr14EventDispatcher($configured))->dispatch($event);

        self::assertSame([$event], $received);
    }
}

final class RefusingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $received = [];

    public function dispatch(object $event): void
    {
        $this->received[] = $event;
    }

    public function hasListeners(string $eventClass): bool
    {
        return false;
    }

    /**
     * @return list<object>
     */
    public function received(): array
    {
        return $this->received;
    }
}

final class StoppedEvent implements GacelaEventInterface, StoppableEventInterface
{
    public function toString(): string
    {
        return 'stopped event';
    }

    public function isPropagationStopped(): bool
    {
        return true;
    }
}

final class UnstoppedEvent implements GacelaEventInterface, StoppableEventInterface
{
    public function toString(): string
    {
        return 'unstopped event';
    }

    public function isPropagationStopped(): bool
    {
        return false;
    }
}
