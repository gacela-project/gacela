<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNameCacheCachedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNameInMemoryCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNamePhpCacheCreatedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;

final class ClassResolverCache
{
    use EventDispatchingCapabilities;

    private static ?CacheInterface $cache = null;

    /**
     * @internal
     */
    public static function resetCache(): void
    {
        self::$cache = null;
    }

    public static function getCache(): CacheInterface
    {
        if (self::$cache instanceof CacheInterface) {
            self::dispatchEvent(new ClassNameCacheCachedEvent());
            return self::$cache;
        }

        if (self::isEnabled()) {
            $cacheDir = Config::getInstance()->getCacheDir();
            self::dispatchEvent(new ClassNamePhpCacheCreatedEvent($cacheDir));
            self::$cache = new ClassNamePhpCache($cacheDir);
        } else {
            self::dispatchEvent(new ClassNameInMemoryCacheCreatedEvent());
            self::$cache = new InMemoryCache(ClassNamePhpCache::class);
        }

        return self::$cache;
    }

    private static function isEnabled(): bool
    {
        return (new GacelaFileCache(Config::getInstance()))->isEnabled();
    }
}
