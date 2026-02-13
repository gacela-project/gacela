<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Config\Config;

final class ClassResolverCache
{
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
            return self::$cache;
        }

        if (self::isEnabled()) {
            $cacheDir = Config::getInstance()->getCacheDir();
            self::$cache = new ClassNamePhpCache($cacheDir);
        } else {
            self::$cache = new InMemoryCache(ClassNamePhpCache::class);
        }

        return self::$cache;
    }

    private static function isEnabled(): bool
    {
        return (new GacelaFileCache(Config::getInstance()))->isEnabled();
    }
}
