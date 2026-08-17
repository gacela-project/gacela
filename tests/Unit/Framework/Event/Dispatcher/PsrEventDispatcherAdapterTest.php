<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Dispatcher;

use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use Gacela\Framework\Event\Dispatcher\PsrEventDispatcherAdapter;
use GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory\FakeEvent;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

final class PsrEventDispatcherAdapterTest extends TestCase
{
    public function test_the_event_reaches_the_psr_dispatcher(): void
    {
        $psr = new RecordingPsrDispatcher();
        $event = new FakeEvent();

        (new PsrEventDispatcherAdapter($psr))->dispatch($event);

        self::assertSame([$event], $psr->received());
    }

    /**
     * PSR-14 has no way to ask what is subscribed: a bus is precisely the thing
     * that knows its listeners while its caller does not. Answering `false`
     * would make every dispatch site skip, and the dispatcher an application
     * deliberately handed over would receive nothing at all -- silently.
     */
    public function test_it_answers_that_anything_may_have_a_listener(): void
    {
        $adapter = new PsrEventDispatcherAdapter(new RecordingPsrDispatcher());

        self::assertTrue($adapter->hasListeners(FakeEvent::class));
        self::assertTrue($adapter->hasListeners('Some\Event\Nobody\Registered'));
    }

    /**
     * The price of the answer above, stated as a test: every guarded dispatch
     * site allocates its event once a PSR-14 bus is installed. It is paid only
     * by an application that installed one, and there is no cheaper honest
     * answer -- see the class docblock.
     */
    public function test_every_event_dispatched_after_the_handover_arrives(): void
    {
        $psr = new RecordingPsrDispatcher();
        $adapter = new PsrEventDispatcherAdapter($psr);

        $first = new FakeEvent();
        $second = new FakeEvent();
        $adapter->dispatch($first);
        $adapter->dispatch($second);

        self::assertSame([$first, $second], $psr->received());
    }

    /**
     * PSR-14 says the dispatcher returns the event, and permits it to be a
     * different object. Gacela's returns nothing, so whatever the bus hands
     * back is dropped here rather than leaking somewhere no caller could use
     * it -- and the dispatch itself still happened, which is the part that
     * matters to the framework.
     */
    public function test_a_bus_answering_with_another_object_changes_nothing(): void
    {
        $psr = new RecordingPsrDispatcher(new FakeEvent());
        $event = new FakeEvent();

        (new PsrEventDispatcherAdapter($psr))->dispatch($event);

        self::assertSame([$event], $psr->received());
    }

    public function test_wrapping_a_psr_dispatcher_adapts_it(): void
    {
        $psr = new RecordingPsrDispatcher();

        $wrapped = PsrEventDispatcherAdapter::wrap($psr);
        $event = new FakeEvent();
        $wrapped->dispatch($event);

        self::assertInstanceOf(PsrEventDispatcherAdapter::class, $wrapped);
        self::assertSame([$event], $psr->received());
    }

    /**
     * A dispatcher that already speaks Gacela's interface is installed as it
     * is. Wrapping it would throw away the `hasListeners()` it answers for
     * itself -- the one thing an adapter around a PSR-14 bus cannot know --
     * and every guarded dispatch site would start allocating.
     */
    public function test_wrapping_a_gacela_dispatcher_hands_back_the_same_object(): void
    {
        $gacela = new NullEventDispatcher();

        self::assertSame($gacela, PsrEventDispatcherAdapter::wrap($gacela));
    }

    /**
     * `void` satisfies PSR-14's untyped `dispatch()`, so one class can carry
     * both interfaces. Gacela's is the richer contract of the two, and taking
     * it keeps such a dispatcher's own `hasListeners()` in charge.
     */
    public function test_a_dispatcher_that_is_both_is_taken_as_the_gacela_one(): void
    {
        $both = new BothInterfacesDispatcher();

        self::assertSame($both, PsrEventDispatcherAdapter::wrap($both));
    }
}

final class RecordingPsrDispatcher implements PsrEventDispatcherInterface
{
    /** @var list<object> */
    private array $received = [];

    /**
     * @param object|null $answer what `dispatch()` hands back instead of the
     *   event, which PSR-14 permits: it declares no return type at all
     */
    public function __construct(
        private readonly ?object $answer = null,
    ) {
    }

    public function dispatch(object $event): object
    {
        $this->received[] = $event;

        return $this->answer ?? $event;
    }

    /**
     * @return list<object>
     */
    public function received(): array
    {
        return $this->received;
    }
}

/**
 * Both contracts at once: `void` satisfies PSR-14's untyped `dispatch()`,
 * whatever the prose around it says the return value means.
 */
final class BothInterfacesDispatcher implements EventDispatcherInterface, PsrEventDispatcherInterface
{
    public function dispatch(object $event): void
    {
    }

    public function hasListeners(string $eventClass): bool
    {
        return false;
    }
}
