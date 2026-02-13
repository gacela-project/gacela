<?php

declare(strict_types=1);

namespace Gacela\Framework\ServiceResolver;

use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Config\Config;

final class DocBlockResolverCache
{
    private static ?CacheInterface $cache = null;

    public static function resetCache(): void
    {
        self::$cache = null;
    }

    public static function getCacheInstance(): CacheInterface
    {
        if (self::$cache instanceof CacheInterface) {
            return self::$cache;
        }

        if (self::isProjectCacheEnabled()) {
            self::$cache = new CustomServicesPhpCache(Config::getInstance()->getCacheDir());
        } else {
            self::$cache = new InMemoryCache(CustomServicesPhpCache::class);
        }

        return self::$cache;
    }

    private static function isProjectCacheEnabled(): bool
    {
        return (new GacelaFileCache(Config::getInstance()))->isEnabled();
    }
}
