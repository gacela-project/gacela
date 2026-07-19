<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Lifecycle;

use Gacela\Framework\Event\Bootstrap\GacelaBootstrapFinishedEvent;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapStartedEvent;
use Gacela\Framework\Event\Cache\CacheClearedEvent;
use Gacela\Framework\Event\Cache\CacheWarmedEvent;
use Gacela\Framework\Event\Config\ConfigInitializedEvent;
use Gacela\Framework\Event\Config\ConfigKeyNotFoundEvent;
use Gacela\Framework\Event\Config\ConfigKeyReadEvent;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\Container\ServiceResolvedEvent;
use Gacela\Framework\Event\Provider\ProviderRegisteredEvent;
use PHPUnit\Framework\TestCase;

final class LifecycleEventGettersTest extends TestCase
{
    public function test_bootstrap_started_event_renders_to_string(): void
    {
        $event = new GacelaBootstrapStartedEvent('/app/root');

        self::assertSame('/app/root', $event->appRootDir());
        self::assertStringContainsString(GacelaBootstrapStartedEvent::class, $event->toString());
    }

    public function test_bootstrap_finished_event_exposes_duration(): void
    {
        $event = new GacelaBootstrapFinishedEvent(12.5);

        self::assertSame(12.5, $event->durationMs());
        self::assertStringContainsString('12.5', $event->toString());
    }

    public function test_config_initialized_event_exposes_key_count(): void
    {
        $event = new ConfigInitializedEvent(42);

        self::assertSame(42, $event->keyCount());
    }

    public function test_config_key_read_event_exposes_key(): void
    {
        $event = new ConfigKeyReadEvent('db.host');

        self::assertSame('db.host', $event->key());
    }

    public function test_config_key_not_found_event_exposes_key(): void
    {
        $event = new ConfigKeyNotFoundEvent('missing.key');

        self::assertSame('missing.key', $event->key());
    }

    public function test_service_resolved_event_exposes_id(): void
    {
        $event = new ServiceResolvedEvent('logger');

        self::assertSame('logger', $event->id());
    }

    public function test_binding_registered_event_exposes_id(): void
    {
        $event = new BindingRegisteredEvent('router');

        self::assertSame('router', $event->id());
    }

    public function test_provider_registered_event_exposes_provider_and_module(): void
    {
        $event = new ProviderRegisteredEvent('App\ModuleA\Provider', 'ModuleA');

        self::assertSame('App\ModuleA\Provider', $event->providerClass());
        self::assertSame('ModuleA', $event->moduleName());
    }

    public function test_cache_cleared_event_exposes_cache_file(): void
    {
        $event = new CacheClearedEvent('/cache/gacela-file.php');

        self::assertSame('/cache/gacela-file.php', $event->cacheFile());
    }

    public function test_cache_warmed_event_exposes_counts(): void
    {
        $event = new CacheWarmedEvent(10, 2);

        self::assertSame(10, $event->moduleCount());
        self::assertSame(2, $event->failedCount());
    }
}
