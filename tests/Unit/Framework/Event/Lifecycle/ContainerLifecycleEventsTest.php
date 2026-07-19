<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Lifecycle;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Event\Container\ServiceResolvedEvent;
use GacelaTest\Fixtures\SpyEventDispatcher;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

final class ContainerLifecycleEventsTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetInstance();
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    public function test_service_resolved_event_dispatched_once_per_service_id(): void
    {
        $spy = $this->bootstrapWithSpy();

        $container = new Container();
        $container->set('my-service', static fn (): stdClass => new stdClass());

        $container->get('my-service');
        $container->get('my-service');

        $resolvedEvents = $spy->dispatchedEventsOf(ServiceResolvedEvent::class);
        self::assertCount(1, $resolvedEvents);
        self::assertSame('my-service', $resolvedEvents[0]->id());
    }

    public function test_no_service_resolved_event_when_nothing_listens(): void
    {
        $spy = $this->bootstrapWithSpy(hasListeners: false);

        $container = new Container();
        $container->set('my-service', static fn (): stdClass => new stdClass());
        $container->get('my-service');

        self::assertSame([], $spy->dispatchedEvents());
    }

    public function test_get_stays_zero_cost_when_nothing_listens(): void
    {
        $this->bootstrapWithSpy(hasListeners: false);

        $container = new Container();
        $container->set('my-service', static fn (): stdClass => new stdClass());
        $container->get('my-service');
        $container->get('my-service');

        // With no listener the resolved-id dedup map must stay empty, so get()
        // adds no per-call bookkeeping nor unbounded memory growth.
        $resolvedServiceIds = new ReflectionProperty(Container::class, 'resolvedServiceIds');
        self::assertSame([], $resolvedServiceIds->getValue($container));
    }

    private function bootstrapWithSpy(bool $hasListeners = true): SpyEventDispatcher
    {
        $spy = new SpyEventDispatcher($hasListeners);

        Config::createWithSetup((new SetupGacela())->setEventDispatcher($spy));

        return $spy;
    }
}
