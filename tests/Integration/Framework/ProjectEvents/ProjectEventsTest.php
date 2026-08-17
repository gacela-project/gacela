<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ProjectEvents;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\EventDispatcherProvider;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\ProjectEvents\Ordering\Domain\OrderPlacedEvent;
use GacelaTest\Integration\Framework\ProjectEvents\Ordering\OrderingFacade;
use GacelaTest\Integration\Framework\ProjectEvents\Ordering\OrderingFactory;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

/**
 * An application dispatching its own events through Gacela's dispatcher.
 *
 * The whole point of the arrangement is that nothing here is special: the
 * dispatcher is a provided dependency like a repository or a clock, the event
 * is a class implementing one interface, and the listener is registered where
 * every other listener is. What the framework contributes is the guard -- an
 * event nobody wants is never built -- and the inheritance matching.
 */
final class ProjectEventsTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_a_factory_is_given_the_dispatcher_the_application_runs_with(): void
    {
        $this->bootstrap();

        self::assertSame(
            EventDispatcherProvider::get(),
            (new OrderingFactory())->getEventDispatcher(),
        );
    }

    /**
     * The module's own container, which is what a Provider is handed and what
     * `getProvidedDependency()` reads. The application container deliberately
     * does not carry it: that one is what `debug:container` and
     * `validate:config` describe, and it should describe the application.
     */
    public function test_the_dispatcher_resolves_from_the_module_container(): void
    {
        $this->bootstrap();

        $factory = new OrderingFactory();

        self::assertSame(EventDispatcherProvider::get(), $factory->getEventDispatcher());
        self::assertFalse(
            Gacela::container()->provides(EventDispatcherInterface::class),
            'the application container should be about the application',
        );
    }

    /**
     * The id is a default, not a reservation: an application that binds
     * something else under it gets what it asked for.
     */
    public function test_a_binding_the_application_declares_for_the_id_wins(): void
    {
        $own = new NullEventDispatcher();

        $this->bootstrap(static function (GacelaConfig $config) use ($own): void {
            $config->addBinding(EventDispatcherInterface::class, $own);
        });

        self::assertSame($own, (new OrderingFactory())->getEventDispatcher());
    }

    public function test_a_module_event_reaches_a_listener_registered_at_bootstrap(): void
    {
        $placed = [];

        $this->bootstrap(static function (GacelaConfig $config) use (&$placed): void {
            $config->registerSpecificListener(
                OrderPlacedEvent::class,
                static function (OrderPlacedEvent $event) use (&$placed): void {
                    $placed[] = $event->reference();
                },
            );
        });

        (new OrderingFacade())->placeOrder('order-1');

        self::assertSame(['order-1'], $placed);
    }

    /**
     * The matching rule is the dispatcher's, so it covers a project's events on
     * the same terms as the framework's: a listener on `GacelaEventInterface`
     * hears everything, including events Gacela knows nothing about.
     */
    public function test_a_listener_on_the_event_interface_hears_a_project_event(): void
    {
        $heard = [];

        $this->bootstrap(static function (GacelaConfig $config) use (&$heard): void {
            $config->registerSpecificListener(
                GacelaEventInterface::class,
                static function (GacelaEventInterface $event) use (&$heard): void {
                    $heard[] = $event::class;
                },
            );
        });

        (new OrderingFacade())->placeOrder('order-2');

        self::assertContains(OrderPlacedEvent::class, $heard);
    }

    /**
     * What the guard buys a project: with nothing listening for it, the event
     * object is never constructed -- the same deal the framework's own dispatch
     * sites get.
     */
    public function test_an_event_nothing_listens_to_is_never_built(): void
    {
        $this->bootstrap();

        self::assertFalse(
            (new OrderingFactory())->getEventDispatcher()->hasListeners(OrderPlacedEvent::class),
        );
    }

    /**
     * A project's own event, on the host's bus. Nothing in the module changes:
     * it dispatches into the dispatcher the application installed.
     */
    public function test_a_project_event_reaches_a_supplied_psr14_bus(): void
    {
        $bus = new class() implements PsrEventDispatcherInterface {
            /** @var list<object> */
            public array $received = [];

            public function dispatch(object $event): object
            {
                $this->received[] = $event;

                return $event;
            }
        };

        $this->bootstrap(static function (GacelaConfig $config) use ($bus): void {
            $config->setEventDispatcher($bus);
        });

        (new OrderingFacade())->placeOrder('order-3');

        $references = [];

        foreach ($bus->received as $event) {
            if ($event instanceof OrderPlacedEvent) {
                $references[] = $event->reference();
            }
        }

        self::assertSame(['order-3'], $references);
    }

    private function bootstrap(?Closure $configFn = null): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);

            if ($configFn instanceof Closure) {
                $configFn($config);
            }
        });
    }
}
