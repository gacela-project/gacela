<?php

declare(strict_types=1);

namespace Gacela\Framework\ServiceResolver;

use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesCacheCachedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesInMemoryCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\CustomServicesPhpCacheCreatedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;

/**
 * Holds the cache of resolved custom services, and decides what backs it: a
 * PHP file when the project has file caching on, an in-memory map otherwise.
 *
 * The counterpart of {@see \Gacela\Framework\ClassResolver\ClassResolverCache},
 * which does the same for resolved class names.
 *
 * Was `DocBlockResolverCache`, which named the wrong thing: nothing here is
 * docblock-specific, and every symbol it touches says custom services --
 * `CustomServicesPhpCache` and the three `CustomServices*` events.
 */
final class ServiceResolverCache
{
    use EventDispatchingCapabilities;

    private static ?CacheInterface $cache = null;

    public static function resetCache(): void
    {
        self::$cache = null;
    }

    public static function getCacheInstance(): CacheInterface
    {
        $cache = self::$cache;
        if ($cache instanceof CacheInterface) {
            if (self::shouldDispatch(CustomServicesCacheCachedEvent::class)) {
                self::dispatchEvent(new CustomServicesCacheCachedEvent());
            }

            return $cache;
        }

        if (self::isProjectCacheEnabled()) {
            if (self::shouldDispatch(CustomServicesPhpCacheCreatedEvent::class)) {
                self::dispatchEvent(new CustomServicesPhpCacheCreatedEvent());
            }
            $cache = new CustomServicesPhpCache(Config::getInstance()->getCacheDir(), Config::getInstance()->getAppRootDir());
        } else {
            if (self::shouldDispatch(CustomServicesInMemoryCacheCreatedEvent::class)) {
                self::dispatchEvent(new CustomServicesInMemoryCacheCreatedEvent());
            }
            $cache = new InMemoryCache(CustomServicesPhpCache::class);
        }

        self::$cache = $cache;

        return $cache;
    }

    private static function isProjectCacheEnabled(): bool
    {
        return (new GacelaFileCache(Config::getInstance()))->isEnabled();
    }
}
