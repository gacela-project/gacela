<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Event;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapFinishedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\EventDispatcherProvider;
use Gacela\Framework\Event\Dispatcher\Psr14EventDispatcher;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

use function array_map;

/**
 * `docs/events.md` documents replacing the dispatcher — "`SetupGacela::setEventDispatcher()`
 * accepts any `EventDispatcherInterface`" — and until now nothing public could
 * reach it: `Gacela::bootstrap()` hands its closure a `GacelaConfig`, which had
 * no such method, and `gacela.php` is handed the same object. The only route
 * was `Config::createWithSetup()`, which is `@internal`.
 */
final class CustomDispatcherFromBootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_a_dispatcher_set_on_the_config_is_the_one_in_use(): void
    {
        $spy = $this->spy();

        $this->bootstrapWith(static function (GacelaConfig $config) use ($spy): void {
            $config->setEventDispatcher($spy);
        });

        self::assertSame($spy, EventDispatcherProvider::get());
    }

    /**
     * The reason the seam is documented: a dispatcher answering `false` keeps
     * the framework from building the event at all, so `dispatch()` is never
     * reached however many sites are passed.
     */
    public function test_declining_every_event_class_allocates_nothing(): void
    {
        $spy = $this->spy();

        $this->bootstrapWith(static function (GacelaConfig $config) use ($spy): void {
            $config->setEventDispatcher($spy);
        });

        (new ConsoleFacade())->getFactory();

        self::assertGreaterThan(0, $spy->asked, 'the dispatcher was never consulted, so this proves nothing');
        self::assertSame(0, $spy->dispatched);
    }

    /**
     * The other way round from what the wording invites. `getEventDispatcher()`
     * is `$this->properties->eventDispatcher ??= SetupEventDispatcher::getDispatcher($this)`,
     * so a supplied dispatcher is never replaced: the switch governs the
     * dispatcher Gacela would *build*, and this is one it does not build.
     *
     * Pinned because the combination is now reachable from a bootstrap closure
     * and reads as if it would silence everything. It does not -- an application
     * that hands over a dispatcher is the thing deciding what runs, and declines
     * in `hasListeners()`.
     */
    public function test_a_supplied_dispatcher_outlives_disabling_the_listeners(): void
    {
        $spy = $this->spy();

        $this->bootstrapWith(static function (GacelaConfig $config) use ($spy): void {
            $config->setEventDispatcher($spy);
            $config->disableEventListeners();
        });

        self::assertSame($spy, EventDispatcherProvider::get());
    }

    /**
     * A dispatcher says what *delivers* the events; a listener says what must
     * *run*. Both were asked for in the same closure, so both happen -- the
     * dispatcher used to win outright, because it was written into the very
     * slot the built one is memoized in and the listeners were never reached.
     */
    public function test_a_supplied_dispatcher_composes_with_the_listeners_registered_beside_it(): void
    {
        $spy = $this->spy();
        $calls = 0;

        $this->bootstrapWith(static function (GacelaConfig $config) use ($spy, &$calls): void {
            $config->setEventDispatcher($spy);
            $config->registerGenericListener(static function () use (&$calls): void {
                ++$calls;
            });
        });

        (new ConsoleFacade())->getFactory();

        self::assertGreaterThan(0, $calls, 'the listener registered beside the dispatcher never ran');
        self::assertGreaterThan(0, $spy->asked, 'the supplied dispatcher was never consulted');
    }

    /**
     * Handing back the dispatcher Gacela derived for this setup is not a
     * handover: composing it with the listeners it already owns would deliver
     * every one of them twice.
     */
    public function test_handing_back_the_derived_dispatcher_does_not_duplicate_its_listeners(): void
    {
        $calls = 0;

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$calls): void {
            $config->registerSpecificListener(
                GacelaBootstrapFinishedEvent::class,
                static function () use (&$calls): void {
                    ++$calls;
                },
            );
        });

        $setup = Config::getInstance()->getSetupGacela();
        self::assertInstanceOf(SetupGacela::class, $setup);
        $setup->setEventDispatcher($setup->getEventDispatcher());

        EventDispatcherProvider::get()->dispatch(new GacelaBootstrapFinishedEvent(1.0));

        self::assertSame(2, $calls, 'once at bootstrap and once here -- any more is a listener registered twice');
    }

    /**
     * The host's own bus, handed over as-is. A Symfony or Laravel application
     * has a PSR-14 dispatcher and no reason to write an adapter for it, so the
     * parameter accepts one and Gacela wraps it.
     */
    public function test_a_psr14_dispatcher_is_accepted_and_receives_the_events(): void
    {
        $bus = new RecordingPsr14Bus();

        $this->bootstrapWith(static function (GacelaConfig $config) use ($bus): void {
            $config->setEventDispatcher($bus);
        });

        self::assertContains(GacelaBootstrapFinishedEvent::class, $bus->receivedClasses());
    }

    /**
     * Same composition as a Gacela dispatcher gets, because by the time the
     * setup holds it there is no difference left: the wrapping happens at the
     * setter, so everything downstream sees one `EventDispatcherInterface`.
     */
    public function test_a_psr14_dispatcher_composes_with_the_listeners_registered_beside_it(): void
    {
        $bus = new RecordingPsr14Bus();
        $calls = 0;

        $this->bootstrapWith(static function (GacelaConfig $config) use ($bus, &$calls): void {
            $config->setEventDispatcher($bus);
            $config->registerSpecificListener(
                GacelaBootstrapFinishedEvent::class,
                static function () use (&$calls): void {
                    ++$calls;
                },
            );
        });

        self::assertSame(1, $calls, 'the listener registered beside the bus never ran');
        self::assertContains(GacelaBootstrapFinishedEvent::class, $bus->receivedClasses());
    }

    /**
     * The other direction: a library that type-hints PSR-14 can be handed the
     * dispatcher this application configured, listeners and all.
     */
    public function test_the_configured_dispatcher_can_be_handed_to_a_psr14_consumer(): void
    {
        $calls = 0;

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$calls): void {
            $config->registerSpecificListener(
                GacelaBootstrapFinishedEvent::class,
                static function () use (&$calls): void {
                    ++$calls;
                },
            );
        });

        $psr = new Psr14EventDispatcher(EventDispatcherProvider::get());
        $event = new GacelaBootstrapFinishedEvent(1.0);

        self::assertSame($event, $psr->dispatch($event));
        self::assertSame(2, $calls, 'once at bootstrap, once through the PSR-14 view of the same dispatcher');
    }

    private function bootstrapWith(callable $setup): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($setup): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $setup($config);
        });
    }

    private function spy(): EventDispatcherInterface
    {
        return new class() implements EventDispatcherInterface {
            public int $asked = 0;

            public int $dispatched = 0;

            public function hasListeners(string $eventClass): bool
            {
                ++$this->asked;

                return false;
            }

            public function dispatch(object $event): void
            {
                ++$this->dispatched;
            }
        };
    }
}

/**
 * The shape a host application already has: PSR-14 and nothing else. No
 * `hasListeners()`, because PSR-14 has no such method to implement.
 */
final class RecordingPsr14Bus implements PsrEventDispatcherInterface
{
    /** @var list<object> */
    private array $received = [];

    public function dispatch(object $event): object
    {
        $this->received[] = $event;

        return $event;
    }

    /**
     * @return list<class-string>
     */
    public function receivedClasses(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->received);
    }
}
