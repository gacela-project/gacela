<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Debug;

use Gacela\Console\Application\Debug\EventCatalog;
use Gacela\Console\Application\Debug\EventInspection;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapStartedEvent;
use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNameCacheCachedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Event\Provider\ProviderRegisteredEvent;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;

final class EventCatalogTest extends TestCase
{
    public function test_it_finds_the_events_the_framework_ships(): void
    {
        $classes = (new EventCatalog())->eventClasses();

        self::assertContains(GacelaBootstrapStartedEvent::class, $classes);
        self::assertContains(ClassNameCacheCachedEvent::class, $classes);
        self::assertContains(AbstractGacelaClassResolverEvent::class, $classes);
    }

    /**
     * Read off the directory rather than listed in the catalog, so a new event
     * appears without anybody remembering it.
     */
    public function test_every_event_class_it_finds_is_a_gacela_event(): void
    {
        $classes = (new EventCatalog())->eventClasses();

        self::assertGreaterThan(20, count($classes));

        foreach ($classes as $class) {
            self::assertTrue(
                is_a($class, GacelaEventInterface::class, true),
                $class . ' is not a Gacela event',
            );
        }
    }

    public function test_the_classes_come_back_sorted(): void
    {
        $classes = (new EventCatalog())->eventClasses();
        $sorted = $classes;
        sort($sorted);

        self::assertSame($sorted, $classes);
    }

    /**
     * Only the dispatcher itself lives in this directory alongside the events,
     * and none of its classes ends in `Event.php`.
     */
    public function test_it_finds_no_dispatcher_class(): void
    {
        $classes = (new EventCatalog())->eventClasses();

        self::assertNotContains(ConfigurableEventDispatcher::class, $classes);
        self::assertNotContains(GacelaEventInterface::class, $classes);
    }

    public function test_an_event_nothing_listens_to_reports_no_listeners(): void
    {
        $inspection = $this->inspectOne(ProviderRegisteredEvent::class, [], 0);

        self::assertFalse($inspection->isWatched());
        self::assertSame(0, $inspection->listenerCount());
        self::assertSame([], $inspection->matchedTargets);
    }

    public function test_a_listener_on_the_event_itself_covers_it(): void
    {
        $inspection = $this->inspectOne(ProviderRegisteredEvent::class, [ProviderRegisteredEvent::class => 2], 0);

        self::assertTrue($inspection->isWatched());
        self::assertSame(2, $inspection->listenerCount());
        self::assertSame([ProviderRegisteredEvent::class => 2], $inspection->matchedTargets);
    }

    /**
     * All of them, not the first that matches: an event can be reached by its
     * own name, by a parent and by the interface at once, and a report naming
     * one of the three sends the reader looking for a registration that is not
     * the one firing.
     */
    public function test_every_target_covering_an_event_is_reported(): void
    {
        $inspection = $this->inspectOne(ResolvedClassCachedEvent::class, [
            AbstractGacelaClassResolverEvent::class => 1,
            GacelaEventInterface::class => 1,
            ResolvedClassCachedEvent::class => 2,
        ], 0);

        self::assertSame([
            AbstractGacelaClassResolverEvent::class => 1,
            GacelaEventInterface::class => 1,
            ResolvedClassCachedEvent::class => 2,
        ], $inspection->matchedTargets);
        self::assertSame(4, $inspection->listenerCount());
    }

    /**
     * The whole reason this command exists: a listener registered against a
     * parent covers events that never name it, so "which registration is this?"
     * has no answer you can grep for.
     */
    public function test_a_listener_on_a_parent_class_covers_the_concrete_event(): void
    {
        $inspection = $this->inspectOne(
            ResolvedClassCachedEvent::class,
            [AbstractGacelaClassResolverEvent::class => 1],
            0,
        );

        self::assertSame([AbstractGacelaClassResolverEvent::class => 1], $inspection->matchedTargets);
    }

    public function test_a_listener_on_the_event_interface_covers_every_event(): void
    {
        $inspection = $this->inspectOne(ProviderRegisteredEvent::class, [GacelaEventInterface::class => 1], 0);

        self::assertSame([GacelaEventInterface::class => 1], $inspection->matchedTargets);
    }

    public function test_a_listener_on_an_unrelated_event_does_not_cover_it(): void
    {
        $inspection = $this->inspectOne(
            ProviderRegisteredEvent::class,
            [AbstractGacelaClassResolverEvent::class => 1],
            0,
        );

        self::assertSame([], $inspection->matchedTargets);
        self::assertFalse($inspection->isWatched());
    }

    public function test_generic_listeners_count_against_every_event(): void
    {
        $inspection = $this->inspectOne(ProviderRegisteredEvent::class, [], 3);

        self::assertTrue($inspection->isWatched());
        self::assertSame(3, $inspection->listenerCount());
        self::assertSame(0, $inspection->specificListenerCount());
        self::assertSame(3, $inspection->genericListenerCount);
    }

    public function test_specific_and_generic_listeners_are_counted_together(): void
    {
        $inspection = $this->inspectOne(ProviderRegisteredEvent::class, [ProviderRegisteredEvent::class => 2], 1);

        self::assertSame(2, $inspection->specificListenerCount());
        self::assertSame(3, $inspection->listenerCount());
    }

    public function test_the_group_is_the_namespace_under_the_event_root(): void
    {
        self::assertSame('Provider', $this->inspectOne(ProviderRegisteredEvent::class, [], 0)->group);
        self::assertSame(
            'ClassResolver\Cache',
            $this->inspectOne(ClassNameCacheCachedEvent::class, [], 0)->group,
        );
    }

    /**
     * No event sits directly in `Gacela\Framework\Event` today, and the group of
     * one that did would be empty rather than the class's own name.
     */
    public function test_an_event_directly_under_the_event_root_has_no_group(): void
    {
        self::assertSame('', $this->inspectOne('Gacela\Framework\Event\SomeRootEvent', [], 0)->group);
    }

    public function test_the_short_name_drops_the_namespace(): void
    {
        self::assertSame(
            'ProviderRegisteredEvent',
            $this->inspectOne(ProviderRegisteredEvent::class, [], 0)->shortName(),
        );
    }

    public function test_a_class_with_no_namespace_is_its_own_short_name(): void
    {
        self::assertSame('SomeRootEvent', $this->inspectOne('SomeRootEvent', [], 0)->shortName());
    }

    public function test_the_abstract_parent_is_marked_as_never_dispatched(): void
    {
        self::assertTrue($this->inspectOne(AbstractGacelaClassResolverEvent::class, [], 0)->isAbstract);
        self::assertFalse($this->inspectOne(ResolvedClassCachedEvent::class, [], 0)->isAbstract);
    }

    /**
     * `is_a()` says a class is itself, so the abstract parent matches its own
     * registration -- and nothing ever dispatches one. Counting it as watched
     * would report the listener in five places when it runs in four.
     */
    public function test_an_abstract_event_is_not_watched_by_the_listener_registered_against_it(): void
    {
        $inspection = $this->inspectOne(
            AbstractGacelaClassResolverEvent::class,
            [AbstractGacelaClassResolverEvent::class => 1],
            0,
        );

        self::assertSame([AbstractGacelaClassResolverEvent::class => 1], $inspection->matchedTargets);
        self::assertSame(1, $inspection->listenerCount());
        self::assertFalse($inspection->isWatched());
    }

    /**
     * A name in the catalog that no longer loads is reported rather than
     * crashing the command: `docs/events.md` and the classes drift, and the
     * command whose job is to say so must survive saying it.
     */
    public function test_a_name_that_is_no_class_is_not_abstract(): void
    {
        self::assertFalse($this->inspectOne('Gacela\Framework\Event\Gone\GoneEvent', [], 0)->isAbstract);
    }

    public function test_the_hot_path_events_are_marked(): void
    {
        self::assertTrue($this->inspectOne(ResolvedClassCachedEvent::class, [], 0)->isHotPath);
        self::assertFalse($this->inspectOne(ResolvedClassCreatedEvent::class, [], 0)->isHotPath);
    }

    public function test_every_hot_path_event_is_a_class_the_catalog_finds(): void
    {
        $classes = (new EventCatalog())->eventClasses();

        foreach (EventCatalog::hotPathEvents() as $hot) {
            self::assertContains($hot, $classes, $hot . ' is marked hot path but is not an event class');
        }
    }

    /**
     * Sorted by group before name, so a namespace prints as one run: sorting the
     * class names instead puts `ClassResolver\Cache` between the abstract parent
     * and the `ResolvedClass*` events beside it, and the header appears twice.
     */
    public function test_inspections_come_back_grouped(): void
    {
        $inspections = (new EventCatalog())->inspect([
            ResolvedClassCreatedEvent::class,
            ClassNameCacheCachedEvent::class,
            AbstractGacelaClassResolverEvent::class,
        ], [], 0);

        self::assertSame([
            AbstractGacelaClassResolverEvent::class,
            ResolvedClassCreatedEvent::class,
            ClassNameCacheCachedEvent::class,
        ], array_map(
            static fn (EventInspection $inspection): string => $inspection->className,
            $inspections,
        ));
    }

    public function test_inspecting_nothing_returns_nothing(): void
    {
        self::assertSame([], (new EventCatalog())->inspect([], [GacelaEventInterface::class => 1], 1));
    }

    /**
     * @param array<string, int> $specificListenerCounts
     */
    private function inspectOne(string $eventClass, array $specificListenerCounts, int $genericListenerCount): EventInspection
    {
        /** @var list<class-string> $classes */
        $classes = [$eventClass];

        $inspections = (new EventCatalog())->inspect($classes, $specificListenerCounts, $genericListenerCount);

        self::assertCount(1, $inspections);

        return $inspections[0];
    }
}
