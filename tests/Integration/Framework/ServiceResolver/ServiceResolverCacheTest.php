<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesCacheCachedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesInMemoryCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesPhpCacheCreatedEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use Gacela\Framework\ServiceResolver\ServiceResolverCache;
use PHPUnit\Framework\TestCase;

final class ServiceResolverCacheTest extends TestCase
{
    /** @var list<class-string> */
    private static array $inMemoryEvents = [];

    protected function setUp(): void
    {
        self::$inMemoryEvents = [];

        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->resetInMemoryCache();

            // This test asserts the exact class-resolver event stream; ignore
            // the framework lifecycle events (bootstrap, config, ...) that a
            // generic listener also receives.
            $config->registerGenericListener(function (GacelaEventInterface $event): void {
                if (str_starts_with($event::class, 'Gacela\Framework\Event\ClassResolver\\')) {
                    $this->saveInMemoryEvent($event);
                }
            });
        });
    }

    public function saveInMemoryEvent(GacelaEventInterface $event): void
    {
        self::$inMemoryEvents[] = $event::class;
    }

    public function test_no_project_cached_enabled(): void
    {
        ServiceResolverCache::getCacheInstance();

        self::assertSame([
            CustomServicesInMemoryCacheCreatedEvent::class,
        ], self::$inMemoryEvents);
    }

    public function test_no_project_cached_enabled_and_cached(): void
    {
        ServiceResolverCache::getCacheInstance();
        ServiceResolverCache::getCacheInstance();

        self::assertSame([
            CustomServicesInMemoryCacheCreatedEvent::class,
            CustomServicesCacheCachedEvent::class,
        ], self::$inMemoryEvents);
    }

    public function test_with_project_cached_enabled(): void
    {
        Config::getInstance()
            ->getSetupGacela()
            ->merge(SetupGacela::fromCallable(static function (GacelaConfig $config): void {
                $config->enableFileCache();
            }));

        ServiceResolverCache::getCacheInstance();

        self::assertSame([
            CustomServicesPhpCacheCreatedEvent::class,
        ], self::$inMemoryEvents);
    }
}
