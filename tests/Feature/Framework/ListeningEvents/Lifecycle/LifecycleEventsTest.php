<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\Lifecycle;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapFinishedEvent;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapStartedEvent;
use Gacela\Framework\Event\Config\ConfigInitializedEvent;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\Container\ServiceResolvedEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Event\Provider\ProviderRegisteredEvent;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;

final class LifecycleEventsTest extends TestCase
{
    /** @var list<GacelaEventInterface> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->events = [];

        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(StringValueInterface::class, StringValue::class);

            $config->registerGenericListener(function (GacelaEventInterface $event): void {
                $this->events[] = $event;
            });
        });
    }

    public function test_bootstrap_dispatches_started_config_initialized_and_finished_in_order(): void
    {
        $startedAt = $this->firstPositionOf(GacelaBootstrapStartedEvent::class);
        $configInitializedAt = $this->firstPositionOf(ConfigInitializedEvent::class);
        $finishedAt = $this->firstPositionOf(GacelaBootstrapFinishedEvent::class);

        self::assertNotNull($startedAt, 'GacelaBootstrapStartedEvent was not dispatched');
        self::assertNotNull($configInitializedAt, 'ConfigInitializedEvent was not dispatched');
        self::assertNotNull($finishedAt, 'GacelaBootstrapFinishedEvent was not dispatched');

        self::assertLessThan($configInitializedAt, $startedAt);
        self::assertLessThan($finishedAt, $configInitializedAt);
    }

    public function test_bootstrap_started_event_carries_app_root_dir(): void
    {
        $started = $this->firstEventOf(GacelaBootstrapStartedEvent::class);

        self::assertInstanceOf(GacelaBootstrapStartedEvent::class, $started);
        self::assertSame(__DIR__, $started->appRootDir());
    }

    public function test_bootstrap_finished_event_carries_positive_duration(): void
    {
        $finished = $this->firstEventOf(GacelaBootstrapFinishedEvent::class);

        self::assertInstanceOf(GacelaBootstrapFinishedEvent::class, $finished);
        self::assertGreaterThan(0.0, $finished->durationMs());
    }

    public function test_resolving_a_module_dispatches_provider_binding_and_service_events(): void
    {
        $facade = new Module\Facade();

        self::assertSame('hello lifecycle', $facade->greet());

        $provider = $this->firstEventOf(ProviderRegisteredEvent::class);
        self::assertInstanceOf(ProviderRegisteredEvent::class, $provider);
        self::assertSame(Module\Provider::class, $provider->providerClass());
        self::assertSame('Module', $provider->moduleName());

        $binding = $this->firstEventOf(BindingRegisteredEvent::class);
        self::assertInstanceOf(BindingRegisteredEvent::class, $binding);
        self::assertSame(StringValueInterface::class, $binding->id());

        $serviceIds = array_map(
            static fn (ServiceResolvedEvent $event): string => $event->id(),
            array_values(array_filter(
                $this->events,
                static fn (GacelaEventInterface $event): bool => $event instanceof ServiceResolvedEvent,
            )),
        );
        self::assertContains(Module\Provider::GREETING, $serviceIds);
    }

    /**
     * @param class-string<GacelaEventInterface> $eventClass
     */
    private function firstPositionOf(string $eventClass): ?int
    {
        foreach ($this->events as $position => $event) {
            if ($event instanceof $eventClass) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @param class-string<GacelaEventInterface> $eventClass
     */
    private function firstEventOf(string $eventClass): ?GacelaEventInterface
    {
        $position = $this->firstPositionOf($eventClass);

        return $position === null ? null : $this->events[$position];
    }
}
