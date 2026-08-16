<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Dispatcher;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Event\Provider\ProviderRegisteredEvent;
use PHPUnit\Framework\TestCase;

final class ConfigurableEventDispatcherTest extends TestCase
{
    public function test_has_no_listeners_when_nothing_registered(): void
    {
        $dispatcher = new ConfigurableEventDispatcher();

        self::assertFalse($dispatcher->hasListeners(ResolvedClassCachedEvent::class));
    }

    public function test_has_no_listeners_when_registered_generic_listeners_are_empty(): void
    {
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerGenericListeners([]);

        self::assertFalse($dispatcher->hasListeners(ResolvedClassCachedEvent::class));
    }

    public function test_has_listeners_for_any_event_class_when_generic_listener_registered(): void
    {
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerGenericListeners([static function (): void {}]);

        self::assertTrue($dispatcher->hasListeners(ResolvedClassCachedEvent::class));
        self::assertTrue($dispatcher->hasListeners(ClassNameNotFoundEvent::class));
    }

    public function test_has_no_listeners_for_an_unrelated_event_class(): void
    {
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(ClassNameNotFoundEvent::class, static function (): void {});

        self::assertTrue($dispatcher->hasListeners(ClassNameNotFoundEvent::class));
        self::assertFalse($dispatcher->hasListeners(ResolvedClassCachedEvent::class));
    }

    public function test_a_specific_listener_receives_the_event_it_was_registered_for(): void
    {
        $received = [];
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(
            ResolvedClassCachedEvent::class,
            static function (ResolvedClassCachedEvent $event) use (&$received): void {
                $received[] = $event;
            },
        );

        $event = $this->resolverEvent();
        $dispatcher->dispatch($event);

        self::assertSame([$event], $received);
    }

    public function test_a_specific_listener_does_not_receive_an_unrelated_event(): void
    {
        $received = [];
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(
            ClassNameNotFoundEvent::class,
            static function () use (&$received): void {
                $received[] = 'called';
            },
        );

        $dispatcher->dispatch($this->resolverEvent());

        self::assertSame([], $received);
    }

    /**
     * The point of #868: registering against the abstract parent covers every
     * resolver event, which is what the docs used to need a generic listener
     * plus an `instanceof` filter for -- the one pattern that taxes the hot
     * path, because it allocates every event at every dispatch site.
     */
    public function test_a_listener_on_the_abstract_parent_receives_the_concrete_event(): void
    {
        $received = [];
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(
            AbstractGacelaClassResolverEvent::class,
            static function (AbstractGacelaClassResolverEvent $event) use (&$received): void {
                $received[] = $event;
            },
        );

        self::assertTrue($dispatcher->hasListeners(ResolvedClassCachedEvent::class));

        $event = $this->resolverEvent();
        $dispatcher->dispatch($event);

        self::assertSame([$event], $received);
    }

    /**
     * `GacelaEventInterface::class` is the typed way to listen to everything:
     * same reach as a generic listener, but the callable is declared against a
     * type static analysis can check.
     */
    public function test_a_listener_on_the_event_interface_receives_every_event(): void
    {
        $received = [];
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(
            GacelaEventInterface::class,
            static function (GacelaEventInterface $event) use (&$received): void {
                $received[] = $event->toString();
            },
        );

        self::assertTrue($dispatcher->hasListeners(ProviderRegisteredEvent::class));

        $dispatcher->dispatch($this->resolverEvent());
        $dispatcher->dispatch(new ProviderRegisteredEvent('ProviderClass', 'ModuleName'));

        self::assertCount(2, $received);
    }

    /**
     * A listener on a sibling branch of the hierarchy is still not called: the
     * new rule is "is-a", not "any event".
     */
    public function test_a_listener_on_a_sibling_event_is_not_called(): void
    {
        $received = [];
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(
            AbstractGacelaClassResolverEvent::class,
            static function () use (&$received): void {
                $received[] = 'called';
            },
        );

        self::assertFalse($dispatcher->hasListeners(ProviderRegisteredEvent::class));

        $dispatcher->dispatch(new ProviderRegisteredEvent('ProviderClass', 'ModuleName'));

        self::assertSame([], $received);
    }

    public function test_generic_listeners_run_before_specific_ones(): void
    {
        $order = [];
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(
            ResolvedClassCachedEvent::class,
            static function () use (&$order): void {
                $order[] = 'specific';
            },
        );
        $dispatcher->registerGenericListeners([static function () use (&$order): void {
            $order[] = 'generic';
        }]);

        $dispatcher->dispatch($this->resolverEvent());

        self::assertSame(['generic', 'specific'], $order);
    }

    public function test_every_matching_listener_runs_in_registration_order(): void
    {
        $order = [];
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(
            AbstractGacelaClassResolverEvent::class,
            static function () use (&$order): void {
                $order[] = 'parent';
            },
        );
        $dispatcher->registerSpecificListener(
            ResolvedClassCachedEvent::class,
            static function () use (&$order): void {
                $order[] = 'concrete';
            },
        );

        $dispatcher->dispatch($this->resolverEvent());

        self::assertSame(['parent', 'concrete'], $order);
    }

    /**
     * The applicable listeners are computed once per concrete event class and
     * memoized, so registering after that has to throw the memo away -- both
     * registration methods, or a listener added later is silently inert.
     */
    public function test_a_specific_listener_registered_after_the_first_lookup_still_fires(): void
    {
        $received = [];
        $dispatcher = new ConfigurableEventDispatcher();

        self::assertFalse($dispatcher->hasListeners(ResolvedClassCachedEvent::class));

        $dispatcher->registerSpecificListener(
            ResolvedClassCachedEvent::class,
            static function () use (&$received): void {
                $received[] = 'called';
            },
        );

        self::assertTrue($dispatcher->hasListeners(ResolvedClassCachedEvent::class));

        $dispatcher->dispatch($this->resolverEvent());

        self::assertSame(['called'], $received);
    }

    public function test_a_generic_listener_registered_after_the_first_lookup_still_fires(): void
    {
        $received = [];
        $dispatcher = new ConfigurableEventDispatcher();

        self::assertFalse($dispatcher->hasListeners(ResolvedClassCachedEvent::class));

        $dispatcher->registerGenericListeners([static function () use (&$received): void {
            $received[] = 'called';
        }]);

        self::assertTrue($dispatcher->hasListeners(ResolvedClassCachedEvent::class));

        $dispatcher->dispatch($this->resolverEvent());

        self::assertSame(['called'], $received);
    }

    /**
     * The memo is keyed by the concrete event class, so a second dispatch of the
     * same class runs the same listeners rather than an empty list.
     */
    public function test_dispatching_the_same_event_class_twice_notifies_twice(): void
    {
        $received = [];
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(
            AbstractGacelaClassResolverEvent::class,
            static function () use (&$received): void {
                $received[] = 'called';
            },
        );

        $dispatcher->dispatch($this->resolverEvent());
        $dispatcher->dispatch($this->resolverEvent());

        self::assertSame(['called', 'called'], $received);
    }

    private function resolverEvent(): ResolvedClassCachedEvent
    {
        return new ResolvedClassCachedEvent(new ClassInfo('CallerClass', 'ModuleName', 'App\\ModuleName'));
    }
}
