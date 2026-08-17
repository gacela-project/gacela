<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\EventListenerTargetCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapStartedEvent;
use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use PHPUnit\Framework\TestCase;

final class EventListenerTargetCheckTest extends TestCase
{
    public function test_a_project_registering_no_specific_listener_has_nothing_to_check(): void
    {
        $result = (new EventListenerTargetCheck([]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no specific listeners registered'], $result->details);
    }

    /**
     * `disableEventListeners()` builds no dispatcher at all, so everything
     * registered silently does not run -- which `docs/events.md` calls the first
     * thing to check when a listener appears dead. The check validated the
     * targets and gave a green tick to listeners that cannot fire.
     */
    public function test_listeners_registered_while_the_dispatcher_is_off_are_reported(): void
    {
        $result = (new EventListenerTargetCheck(
            [GacelaBootstrapStartedEvent::class],
            dispatcherEnabled: false,
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            ['1 listener(s) registered, but event listeners are disabled -- none of them runs'],
            $result->details,
        );
    }

    /**
     * A generic listener is registered against no target, so the target list is
     * empty and the check used to return early -- the one shape where every
     * listener in the project is invisible to it.
     */
    public function test_a_generic_listener_alone_is_reported_when_the_dispatcher_is_off(): void
    {
        $result = (new EventListenerTargetCheck([], genericListenerCount: 2, dispatcherEnabled: false))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            ['2 listener(s) registered, but event listeners are disabled -- none of them runs'],
            $result->details,
        );
    }

    /**
     * Disabling with nothing registered is an ordinary production setting, not
     * a fault: there is no declaration going unheard.
     */
    public function test_the_dispatcher_being_off_with_nothing_registered_is_fine(): void
    {
        $result = (new EventListenerTargetCheck([], dispatcherEnabled: false))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }

    /**
     * Both faults are worth saying: the targets still have to be right for the
     * day the switch is turned back on.
     */
    public function test_an_unfireable_target_is_still_reported_while_the_dispatcher_is_off(): void
    {
        $result = (new EventListenerTargetCheck(
            ['App\Event\Mispelled'],
            dispatcherEnabled: false,
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertCount(2, $result->details);
        self::assertStringContainsString('event listeners are disabled', $result->details[0]);
        self::assertStringContainsString('names no class or interface', $result->details[1]);
        self::assertStringContainsString('disableEventListeners()', $result->remediation);
    }

    public function test_a_concrete_event_class_passes(): void
    {
        $result = (new EventListenerTargetCheck([GacelaBootstrapStartedEvent::class]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 listener target(s) name a known event type'], $result->details);
    }

    /**
     * The dispatcher matches by inheritance since #868, so an interface is the
     * typed way to listen to a whole family -- `GacelaEventInterface::class`
     * covers every event there is. The check used to warn about exactly this.
     */
    public function test_an_interface_passes_because_events_implementing_it_are_matched(): void
    {
        $result = (new EventListenerTargetCheck([GacelaEventInterface::class]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 listener target(s) name a known event type'], $result->details);
    }

    public function test_an_abstract_parent_passes_because_its_subclasses_are_matched(): void
    {
        $result = (new EventListenerTargetCheck([AbstractGacelaClassResolverEvent::class]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 listener target(s) name a known event type'], $result->details);
    }

    public function test_a_name_that_is_no_class_at_all_is_reported(): void
    {
        $result = (new EventListenerTargetCheck(['App\Event\Mispelled']))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['App\Event\Mispelled names no class or interface'], $result->details);
        self::assertStringContainsString('EventClass::class', $result->remediation);
    }

    /**
     * A type that exists and can match is waiting, not broken, whether it is
     * concrete, abstract or an interface.
     */
    public function test_every_unfireable_target_is_named_and_the_valid_ones_are_not(): void
    {
        $result = (new EventListenerTargetCheck([
            GacelaBootstrapStartedEvent::class,
            GacelaEventInterface::class,
            'App\Event\Mispelled',
        ]))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['App\Event\Mispelled names no class or interface'], $result->details);
    }

    /**
     * A project's own event is an event because it says
     * `implements GacelaEventInterface`. Without that it is an ordinary class:
     * the registration is accepted, the dispatcher can never match it, and
     * nothing said so until the catalog was here to judge against.
     */
    public function test_a_target_that_is_not_an_event_type_is_reported(): void
    {
        $result = (new EventListenerTargetCheck(
            [NotAnEvent::class],
            knownEventClasses: [GacelaBootstrapStartedEvent::class],
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            [NotAnEvent::class . ' is not an event type, and no known event extends or implements it'
                . ' -- so nothing can ever match it'],
            $result->details,
        );
        self::assertStringContainsString('GacelaEventInterface', $result->remediation);
    }

    /**
     * The project's events are in the catalog too, so a listener on one is a
     * listener like any other -- the tooling must not contradict the very use
     * it is there to support.
     */
    public function test_a_target_that_is_a_project_event_passes(): void
    {
        $result = (new EventListenerTargetCheck(
            [ProjectEvent::class],
            knownEventClasses: [GacelaBootstrapStartedEvent::class, ProjectEvent::class],
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 listener target(s) name a known event type'], $result->details);
    }

    /**
     * A marker interface an event implements is a legitimate target: it is not
     * an event type itself, and the dispatcher matches it by inheritance all
     * the same. Answering from `GacelaEventInterface` alone would report every
     * one of those as dead.
     */
    public function test_a_marker_interface_a_known_event_implements_passes(): void
    {
        $result = (new EventListenerTargetCheck(
            [AuditableInterface::class],
            knownEventClasses: [AuditedProjectEvent::class],
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }

    /**
     * With no catalog to judge against, the old answer stands: only a name that
     * is no type at all is reported. `doctor` skips the project scan when
     * nothing is registered, and a check with an empty catalog must not start
     * calling every target dead.
     */
    public function test_without_a_catalog_a_type_that_exists_is_left_alone(): void
    {
        $result = (new EventListenerTargetCheck([NotAnEvent::class]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }
}

/**
 * A real class that is not an event: the shape of forgetting the interface.
 */
final class NotAnEvent
{
}

interface AuditableInterface
{
}

final class ProjectEvent implements GacelaEventInterface
{
    public function toString(): string
    {
        return 'a project event';
    }
}

final class AuditedProjectEvent implements AuditableInterface, GacelaEventInterface
{
    public function toString(): string
    {
        return 'an audited project event';
    }
}
