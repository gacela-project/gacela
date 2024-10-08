<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\Config;
use Gacela\Framework\DocBlockResolver\DocBlockResolverCache;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesCacheCachedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesInMemoryCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesPhpCacheCreatedEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class DocBlockResolverCacheTest extends TestCase
{
    /** @var list<class-string> */
    private static array $inMemoryEvents = [];

    protected function setUp(): void
    {
        self::$inMemoryEvents = [];

        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->resetInMemoryCache();

            $config->registerGenericListener(function (GacelaEventInterface $event): void {
                $this->saveInMemoryEvent($event);
            });
        });
    }

    public function saveInMemoryEvent(GacelaEventInterface $event): void
    {
        self::$inMemoryEvents[] = $event::class;
    }

    public function test_no_project_cached_enabled(): void
    {
        DocBlockResolverCache::getCacheInstance();

        self::assertSame([
            CustomServicesInMemoryCacheCreatedEvent::class,
        ], self::$inMemoryEvents);
    }

    public function test_no_project_cached_enabled_and_cached(): void
    {
        DocBlockResolverCache::getCacheInstance();
        DocBlockResolverCache::getCacheInstance();

        self::assertSame([
            CustomServicesInMemoryCacheCreatedEvent::class,
            CustomServicesCacheCachedEvent::class,
        ], self::$inMemoryEvents);
    }

    public function test_with_project_cached_enabled(): void
    {
        Config::getInstance()
            ->getSetupGacela()
            ->combine(SetupGacela::fromCallable(static function (GacelaConfig $config): void {
                $config->enableFileCache();
            }));

        DocBlockResolverCache::getCacheInstance();

        self::assertSame([
            CustomServicesPhpCacheCreatedEvent::class,
        ], self::$inMemoryEvents);
    }
}
