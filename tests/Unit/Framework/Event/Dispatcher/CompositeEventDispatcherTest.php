<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Dispatcher;

use Gacela\Framework\Event\Dispatcher\CompositeEventDispatcher;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory\FakeEvent;
use PHPUnit\Framework\TestCase;

final class CompositeEventDispatcherTest extends TestCase
{
    public function test_both_sides_receive_the_event(): void
    {
        $order = [];

        $configured = new ConfigurableEventDispatcher();
        $configured->registerGenericListeners([static function () use (&$order): void {
            $order[] = 'configured';
        }]);
        $supplied = new SpyDispatcher(static function () use (&$order): void {
            $order[] = 'supplied';
        });

        (new CompositeEventDispatcher($configured, $supplied))->dispatch(new FakeEvent());

        self::assertSame(['configured', 'supplied'], $order);
    }

    /**
     * The order is part of the contract: everything the configuration declared
     * has observed the event before it leaves for the application's own bus.
     */
    public function test_the_configured_listeners_run_before_the_supplied_dispatcher(): void
    {
        $seenBySupplied = null;
        $configuredRan = false;

        $configured = new ConfigurableEventDispatcher();
        $configured->registerGenericListeners([static function () use (&$configuredRan): void {
            $configuredRan = true;
        }]);
        $supplied = new SpyDispatcher(static function () use (&$configuredRan, &$seenBySupplied): void {
            $seenBySupplied = $configuredRan;
        });

        (new CompositeEventDispatcher($configured, $supplied))->dispatch(new FakeEvent());

        self::assertTrue($seenBySupplied, 'the supplied dispatcher was told before the configured listeners ran');
    }

    public function test_it_has_listeners_when_only_the_configured_side_does(): void
    {
        $configured = new ConfigurableEventDispatcher();
        $configured->registerSpecificListener(FakeEvent::class, static function (): void {});

        $composite = new CompositeEventDispatcher($configured, new NullEventDispatcher());

        self::assertTrue($composite->hasListeners(FakeEvent::class));
    }

    /**
     * The half the hot-path guard would otherwise drop: with the answer taken
     * from the configured listeners alone, an application that supplied a
     * dispatcher and registered nothing would see the events it asked for
     * skipped before they were ever allocated.
     */
    public function test_it_has_listeners_when_only_the_supplied_side_does(): void
    {
        $composite = new CompositeEventDispatcher(
            new ConfigurableEventDispatcher(),
            new SpyDispatcher(static function (): void {}),
        );

        self::assertTrue($composite->hasListeners(FakeEvent::class));
    }

    public function test_it_has_no_listeners_when_neither_side_does(): void
    {
        $composite = new CompositeEventDispatcher(
            new ConfigurableEventDispatcher(),
            new NullEventDispatcher(),
        );

        self::assertFalse($composite->hasListeners(FakeEvent::class));
    }

    /**
     * `docs/events.md` promises that returning false from `hasListeners()` is
     * how a supplied dispatcher goes quiet. A configured listener asking for
     * the same event must not smuggle it through.
     */
    public function test_a_supplied_dispatcher_that_declines_the_class_is_not_told(): void
    {
        $configuredRan = false;
        $suppliedTold = false;

        $configured = new ConfigurableEventDispatcher();
        $configured->registerGenericListeners([static function () use (&$configuredRan): void {
            $configuredRan = true;
        }]);
        $supplied = new SpyDispatcher(static function () use (&$suppliedTold): void {
            $suppliedTold = true;
        }, wants: false);

        (new CompositeEventDispatcher($configured, $supplied))->dispatch(new FakeEvent());

        self::assertTrue($configuredRan, 'the configured listener must still run');
        self::assertFalse($suppliedTold);
    }
}

final class SpyDispatcher implements EventDispatcherInterface
{
    /** @var callable */
    private $onDispatch;

    public function __construct(
        callable $onDispatch,
        private readonly bool $wants = true,
    ) {
        $this->onDispatch = $onDispatch;
    }

    public function dispatch(object $event): void
    {
        ($this->onDispatch)($event);
    }

    public function hasListeners(string $eventClass): bool
    {
        return $this->wants;
    }
}
