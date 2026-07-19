<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Lifecycle;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Config\ConfigInitializedEvent;
use Gacela\Framework\Event\Config\ConfigKeyNotFoundEvent;
use Gacela\Framework\Event\Config\ConfigKeyReadEvent;
use Gacela\Framework\Exception\ConfigException;
use GacelaTest\Fixtures\SpyEventDispatcher;
use PHPUnit\Framework\TestCase;

final class ConfigLifecycleEventsTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetInstance();
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    public function test_init_dispatches_config_initialized_event(): void
    {
        $spy = $this->bootstrapWithSpy();

        Config::getInstance()->init();

        self::assertCount(1, $spy->dispatchedEventsOf(ConfigInitializedEvent::class));
    }

    public function test_get_with_default_dispatches_read_and_not_found_events(): void
    {
        $spy = $this->bootstrapWithSpy();

        $value = Config::getInstance()->get('unknown-key', 'fallback');

        self::assertSame('fallback', $value);

        $readEvents = $spy->dispatchedEventsOf(ConfigKeyReadEvent::class);
        self::assertCount(1, $readEvents);
        self::assertSame('unknown-key', $readEvents[0]->key());

        $notFoundEvents = $spy->dispatchedEventsOf(ConfigKeyNotFoundEvent::class);
        self::assertCount(1, $notFoundEvents);
        self::assertSame('unknown-key', $notFoundEvents[0]->key());
    }

    public function test_get_missing_key_without_default_dispatches_not_found_and_throws(): void
    {
        $spy = $this->bootstrapWithSpy();

        try {
            Config::getInstance()->get('missing-key');
            self::fail('Expected ConfigException was not thrown');
        } catch (ConfigException) {
        }

        $notFoundEvents = $spy->dispatchedEventsOf(ConfigKeyNotFoundEvent::class);
        self::assertCount(1, $notFoundEvents);
        self::assertSame('missing-key', $notFoundEvents[0]->key());
    }

    public function test_no_events_dispatched_when_nothing_listens(): void
    {
        $spy = $this->bootstrapWithSpy(hasListeners: false);

        Config::getInstance()->get('unknown-key', 'fallback');

        self::assertSame([], $spy->dispatchedEvents());
    }

    private function bootstrapWithSpy(bool $hasListeners = true): SpyEventDispatcher
    {
        $spy = new SpyEventDispatcher($hasListeners);

        Config::createWithSetup((new SetupGacela())->setEventDispatcher($spy));
        Config::getInstance()->setAppRootDir(__DIR__);

        return $spy;
    }
}
