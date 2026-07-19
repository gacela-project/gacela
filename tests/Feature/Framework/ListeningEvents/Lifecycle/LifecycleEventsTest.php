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
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomInterface;
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
            $config->addFactory('a-factory', static fn (): StringValue => new StringValue('factory'));
            $config->addProtected('a-protected', static fn (): string => 'protected');
            $config->addAlias('an-alias', StringValueInterface::class);
            $config->addLazy('a-lazy', static fn (): StringValue => new StringValue('lazy'));
            $config->when(StringValue::class)->needs(CustomInterface::class)->give(CustomClass::class);

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
        // Upper bound guards the duration math: a broken hrtime diff or a wrong
        // ns->ms conversion yields an astronomically large number.
        self::assertLessThan(60_000.0, $finished->durationMs());
    }

    public function test_every_binding_flavor_dispatches_binding_registered_event(): void
    {
        // Force the main container to be built, which registers bindings,
        // factories, protected services, aliases, contextual bindings,
        // and lazy services.
        self::assertInstanceOf(StringValue::class, Gacela::get('a-factory'));

        $bindingIds = array_map(
            static fn (BindingRegisteredEvent $event): string => $event->id(),
            array_values(array_filter(
                $this->events,
                static fn (GacelaEventInterface $event): bool => $event instanceof BindingRegisteredEvent,
            )),
        );

        self::assertContains(StringValueInterface::class, $bindingIds, 'plain binding');
        self::assertContains('a-factory', $bindingIds, 'factory binding');
        self::assertContains('a-protected', $bindingIds, 'protected binding');
        self::assertContains('an-alias', $bindingIds, 'alias binding');
        self::assertContains('a-lazy', $bindingIds, 'lazy binding');
        self::assertContains(CustomInterface::class, $bindingIds, 'contextual binding');
    }

    public function test_resolving_a_module_dispatches_provider_binding_and_service_events(): void
    {
        $facade = new Module\Facade();

        self::assertSame('hello lifecycle', $facade->greet());

        $providerEvents = array_values(array_filter(
            $this->events,
            static fn (GacelaEventInterface $event): bool => $event instanceof ProviderRegisteredEvent,
        ));
        // Exactly one: the BC DependencyProvider resolver returns the same cached
        // provider instance and must not re-report it.
        self::assertCount(1, $providerEvents);
        self::assertSame(Module\Provider::class, $providerEvents[0]->providerClass());
        self::assertSame('Module', $providerEvents[0]->moduleName());

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

    public function test_bc_dependency_provider_dispatches_provider_registered_event(): void
    {
        self::assertSame('hello bc lifecycle', (new ModuleBc\Facade())->greet());

        $providerClasses = array_map(
            static fn (ProviderRegisteredEvent $event): string => $event->providerClass(),
            array_values(array_filter(
                $this->events,
                static fn (GacelaEventInterface $event): bool => $event instanceof ProviderRegisteredEvent,
            )),
        );
        self::assertContains(ModuleBc\DependencyProvider::class, $providerClasses);
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
