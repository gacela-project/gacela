<?php

declare(strict_types=1);

namespace Gacela\Framework\DocBlockResolver;

use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesCacheCachedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesInMemoryCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesPhpCacheCreatedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;

final class DocBlockResolverCache
{
    use EventDispatchingCapabilities;

    private static ?CacheInterface $cache = null;

    public static function resetCache(): void
    {
        self::$cache = null;
    }

    public static function getCacheInstance(): CacheInterface
    {
        if (self::$cache instanceof CacheInterface) {
            self::dispatchEvent(new CustomServicesCacheCachedEvent());

            return self::$cache;
        }

        if (self::isProjectCacheEnabled()) {
            self::dispatchEvent(new CustomServicesPhpCacheCreatedEvent());
            self::$cache = new CustomServicesPhpCache(Config::getInstance()->getCacheDir());
        } else {
            self::dispatchEvent(new CustomServicesInMemoryCacheCreatedEvent());
            self::$cache = new InMemoryCache(CustomServicesPhpCache::class);
        }

        return self::$cache;
    }

    private static function isProjectCacheEnabled(): bool
    {
        return (new GacelaFileCache(Config::getInstance()))->isEnabled();
    }
}
