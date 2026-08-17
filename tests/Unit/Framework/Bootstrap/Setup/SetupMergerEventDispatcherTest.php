<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Event\Dispatcher\CompositeEventDispatcher;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory\FakeEvent;
use PHPUnit\Framework\TestCase;

/**
 * What a merge does to the listeners and to the dispatcher.
 *
 * Two bugs lived here, and both were silent. The merger registered the incoming
 * listeners straight onto the dispatcher and never wrote them onto the merged
 * setup, so `doctor` and `debug:events` -- which read the setup -- answered
 * "nothing is listening" about listeners that were running (#887). And when the
 * incoming setup brought listeners, it replaced whatever dispatcher was
 * installed with a `ConfigurableEventDispatcher` to hold them, which an
 * application's own dispatcher could never be: handing one over and registering
 * a listener in `gacela.php` threw the handover away (#888).
 *
 * The counters are counters rather than flags on purpose: the fix makes the
 * setup the record and derives the dispatcher from it, and the way to get that
 * wrong is to keep registering as you go *and* record -- which fails nothing
 * except by delivering everything twice.
 */
final class SetupMergerEventDispatcherTest extends TestCase
{
    public function test_specific_listeners_from_the_merged_setup_are_recorded_on_it(): void
    {
        $listener = static function (): void {};

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
        });
        $other = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($listener): void {
            $config->registerSpecificListener(FakeEvent::class, $listener);
        });

        $setup->merge($other);

        self::assertSame([FakeEvent::class => [$listener]], $setup->getSpecificListeners());
    }

    public function test_generic_listeners_from_the_merged_setup_are_recorded_on_it(): void
    {
        $listener = static function (): void {};

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
        });
        $other = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($listener): void {
            $config->registerGenericListener($listener);
        });

        $setup->merge($other);

        self::assertSame([$listener], $setup->getGenericListeners());
    }

    public function test_a_merged_specific_listener_runs_exactly_once_per_event(): void
    {
        $calls = 0;

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
        });
        $other = SetupGacela::fromCallable(static function (GacelaConfig $config) use (&$calls): void {
            $config->registerSpecificListener(FakeEvent::class, static function () use (&$calls): void {
                ++$calls;
            });
        });

        $setup->merge($other);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertSame(1, $calls);
    }

    public function test_a_merged_generic_listener_runs_exactly_once_per_event(): void
    {
        $calls = 0;

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
        });
        $other = SetupGacela::fromCallable(static function (GacelaConfig $config) use (&$calls): void {
            $config->registerGenericListener(static function () use (&$calls): void {
                ++$calls;
            });
        });

        $setup->merge($other);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertSame(1, $calls);
    }

    /**
     * The arrangement the framework actually boots in: the closure is the base
     * and `gacela.php` merges onto it. Each side registered once, so each side
     * runs once.
     */
    public function test_a_listener_on_each_side_of_the_merge_runs_exactly_once(): void
    {
        $fromClosure = 0;
        $fromFile = 0;

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config) use (&$fromClosure): void {
            $config->registerGenericListener(static function () use (&$fromClosure): void {
                ++$fromClosure;
            });
        });
        $other = SetupGacela::fromCallable(static function (GacelaConfig $config) use (&$fromFile): void {
            $config->registerSpecificListener(FakeEvent::class, static function () use (&$fromFile): void {
                ++$fromFile;
            });
        });

        $setup->merge($other);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertSame(1, $fromClosure, 'the listener from the base setup ran the wrong number of times');
        self::assertSame(1, $fromFile, 'the listener from the merged setup ran the wrong number of times');
    }

    /**
     * Two registrations of one callable are two registrations. Nothing
     * deduplicates them, and a merge must not turn them into three.
     */
    public function test_the_same_listener_registered_on_both_sides_runs_once_per_registration(): void
    {
        $calls = 0;
        $listener = static function () use (&$calls): void {
            ++$calls;
        };

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($listener): void {
            $config->registerSpecificListener(FakeEvent::class, $listener);
        });
        $other = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($listener): void {
            $config->registerSpecificListener(FakeEvent::class, $listener);
        });

        $setup->merge($other);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertSame(2, $calls);
    }

    /**
     * A dispatcher only ever set through `setEventDispatcher()` -- the generic
     * listeners were never touched, so they are null rather than an empty list,
     * and the merge has to cope with the asymmetry.
     */
    public function test_a_setup_whose_generic_listeners_were_never_set_can_be_merged(): void
    {
        $calls = 0;

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
        });

        $other = new SetupGacela();
        $other->setAreEventListenersEnabled(true);
        $other->setSpecificListeners([FakeEvent::class => [static function () use (&$calls): void {
            ++$calls;
        }]]);

        $setup->merge($other);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertSame(1, $calls);
    }

    public function test_a_setup_whose_specific_listeners_were_never_set_can_be_merged(): void
    {
        $calls = 0;

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
        });

        $other = new SetupGacela();
        $other->setAreEventListenersEnabled(true);
        $other->setGenericListeners([static function () use (&$calls): void {
            ++$calls;
        }]);

        $setup->merge($other);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertSame(1, $calls);
    }

    /**
     * #888. `ConfigurableEventDispatcher` is `final`, so the branch that kept
     * the installed dispatcher could only ever be taken by one the framework
     * had built itself.
     */
    public function test_a_supplied_dispatcher_survives_a_merge_that_brings_listeners(): void
    {
        $supplied = new RecordingDispatcher();

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($supplied): void {
            $config->setEventDispatcher($supplied);
        });
        $other = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->registerSpecificListener(FakeEvent::class, static function (): void {});
        });

        $setup->merge($other);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertSame([FakeEvent::class], $supplied->received);
    }

    public function test_the_configured_listeners_run_and_the_supplied_dispatcher_is_still_told(): void
    {
        $calls = 0;
        $supplied = new RecordingDispatcher();

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($supplied): void {
            $config->setEventDispatcher($supplied);
        });
        $other = SetupGacela::fromCallable(static function (GacelaConfig $config) use (&$calls): void {
            $config->registerSpecificListener(FakeEvent::class, static function () use (&$calls): void {
                ++$calls;
            });
        });

        $setup->merge($other);

        self::assertInstanceOf(CompositeEventDispatcher::class, $setup->getEventDispatcher());

        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertSame(1, $calls, 'the listener from `gacela.php` did not run exactly once');
        self::assertSame([FakeEvent::class], $supplied->received);
    }

    /**
     * `gacela.php` is as natural a place to hand over a dispatcher as the
     * bootstrap closure is, and the merger used to look at neither.
     */
    public function test_a_dispatcher_supplied_by_the_merged_setup_is_adopted(): void
    {
        $supplied = new RecordingDispatcher();

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
        });
        $other = SetupGacela::fromCallable(static function (GacelaConfig $config) use ($supplied): void {
            $config->setEventDispatcher($supplied);
        });

        $setup->merge($other);

        self::assertSame($supplied, $setup->getEventDispatcher());
    }

    /**
     * The dispatcher is derived and memoized, and `Gacela::bootstrap()` derives
     * it early -- it dispatches its first event before `gacela.php` has been
     * read. So a handover arriving after that has to throw the memo away, or it
     * is recorded and never used, which is the shape #866 hid in.
     */
    public function test_a_dispatcher_supplied_after_one_was_derived_takes_effect(): void
    {
        $supplied = new RecordingDispatcher();

        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->registerSpecificListener(FakeEvent::class, static function (): void {});
        });

        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        $setup->setEventDispatcher($supplied);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertSame([FakeEvent::class], $supplied->received);
    }

    /**
     * Nothing supplied and listeners registered: exactly what it built before,
     * with no composition in the way of the hot-path guard.
     */
    public function test_without_a_supplied_dispatcher_the_merge_builds_the_configurable_one(): void
    {
        $setup = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
        });
        $other = SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            $config->registerSpecificListener(FakeEvent::class, static function (): void {});
        });

        $setup->merge($other);

        self::assertInstanceOf(ConfigurableEventDispatcher::class, $setup->getEventDispatcher());
    }
}

/**
 * An application's own bus, in the shape Gacela is handed one.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<class-string> */
    public array $received = [];

    public function dispatch(object $event): void
    {
        $this->received[] = $event::class;
    }

    public function hasListeners(string $eventClass): bool
    {
        return true;
    }
}
