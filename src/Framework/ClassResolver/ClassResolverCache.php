<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\Cache\BootstrapFingerprint;
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
        $cache = self::$cache;
        if ($cache instanceof CacheInterface) {
            if (self::shouldDispatch(ClassNameCacheCachedEvent::class)) {
                self::dispatchEvent(new ClassNameCacheCachedEvent());
            }

            return $cache;
        }

        if (self::isEnabled()) {
            $cacheDir = Config::getInstance()->getCacheDir();
            if (self::shouldDispatch(ClassNamePhpCacheCreatedEvent::class)) {
                self::dispatchEvent(new ClassNamePhpCacheCreatedEvent($cacheDir));
            }

            $cache = new ClassNamePhpCache(
                $cacheDir,
                Config::getInstance()->getAppRootDir(),
                self::bootstrapFingerprint(),
            );
        } else {
            if (self::shouldDispatch(ClassNameInMemoryCacheCreatedEvent::class)) {
                self::dispatchEvent(new ClassNameInMemoryCacheCreatedEvent());
            }

            $cache = new InMemoryCache(ClassNamePhpCache::class);
        }

        self::$cache = $cache;

        return $cache;
    }

    /**
     * What this bootstrap resolves is decided by its project namespaces and
     * suffix types; the fingerprint keys the on-disk file by them, so two
     * bootstraps of one app root stop answering for each other (#681).
     * `ServiceResolverCache` passes none on purpose: custom-service entries
     * derive from the caller's source alone, so every bootstrap computes the
     * same map, and fragmenting it would buy nothing.
     *
     * Public so doctor's staleness check inspects the file this bootstrap
     * actually reads, not a fingerprint-less path nothing writes anymore.
     *
     * @internal
     */
    public static function bootstrapFingerprint(): string
    {
        return BootstrapFingerprint::compute(
            Config::getInstance()->getSetupGacela()->getProjectNamespaces(),
            Config::getInstance()->getFactory()->createGacelaFileConfig()->getSuffixTypes(),
        );
    }

    private static function isEnabled(): bool
    {
        return (new GacelaFileCache(Config::getInstance()))->isEnabled();
    }
}
