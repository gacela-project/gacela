<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\EventListenerTargetCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapStartedEvent;
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

    public function test_a_concrete_event_class_passes(): void
    {
        $result = (new EventListenerTargetCheck([GacelaBootstrapStartedEvent::class]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 listener target(s) name a concrete event'], $result->details);
    }

    /**
     * The case this exists for. `Container::afterResolving()` matches by
     * instanceof, so registering against a contract looks reasonable -- and the
     * dispatcher matches `$event::class`, so it never runs.
     */
    public function test_an_interface_is_reported_even_though_events_implement_it(): void
    {
        $result = (new EventListenerTargetCheck([GacelaEventInterface::class]))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            [GacelaEventInterface::class . ' is an interface, and events are matched by exact class'],
            $result->details,
        );
        self::assertStringContainsString('registerGenericListener()', $result->remediation);
    }

    public function test_a_name_that_is_no_class_at_all_is_reported(): void
    {
        $result = (new EventListenerTargetCheck(['App\Event\Mispelled']))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['App\Event\Mispelled names no class'], $result->details);
    }

    public function test_an_abstract_class_is_reported(): void
    {
        $result = (new EventListenerTargetCheck([AbstractFixtureEvent::class]))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            [AbstractFixtureEvent::class . ' is abstract, so no dispatched event can be exactly it'],
            $result->details,
        );
    }

    /**
     * A concrete event nothing dispatches is waiting, not broken: only names
     * that can never equal `$event::class` are reported.
     */
    public function test_every_unfireable_target_is_named_and_the_valid_one_is_not(): void
    {
        $result = (new EventListenerTargetCheck([
            GacelaBootstrapStartedEvent::class,
            GacelaEventInterface::class,
            'App\Event\Mispelled',
        ]))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertCount(2, $result->details);
        self::assertStringNotContainsString(GacelaBootstrapStartedEvent::class, implode('|', $result->details));
    }
}

abstract class AbstractFixtureEvent
{
}
