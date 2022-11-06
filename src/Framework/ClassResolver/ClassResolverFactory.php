<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidator;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidatorInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithModulePrefix;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithoutModulePrefix;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNameCacheCachedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNameInMemoryCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNamePhpCacheCreatedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;

final class ClassResolverFactory
{
    use EventDispatchingCapabilities;

    private static ?CacheInterface $cache = null;

    private GacelaFileCache $gacelaCache;

    private SetupGacelaInterface $setupGacela;

    public function __construct(
        GacelaFileCache $gacelaCache,
        SetupGacelaInterface $setupGacela
    ) {
        $this->gacelaCache = $gacelaCache;
        $this->setupGacela = $setupGacela;
    }

    /**
     * @internal
     */
    public static function resetCache(): void
    {
        self::$cache = null;
    }

    public function createClassNameFinder(): ClassNameFinderInterface
    {
        return new ClassNameFinder(
            $this->createClassValidator(),
            $this->createFinderRules(),
            $this->getCache(),
            $this->getProjectNamespaces()
        );
    }

    private function createClassValidator(): ClassValidatorInterface
    {
        return new ClassValidator();
    }

    /**
     * @return list<FinderRuleInterface>
     */
    private function createFinderRules(): array
    {
        return [
            new FinderRuleWithModulePrefix(),
            new FinderRuleWithoutModulePrefix(),
        ];
    }

    private function getCache(): CacheInterface
    {
        if (self::$cache !== null) {
            $this->dispatchEvent(new ClassNameCacheCachedEvent());
            return self::$cache;
        }

        if ($this->gacelaCache->isEnabled()) {
            $this->dispatchEvent(new ClassNamePhpCacheCreatedEvent());

            self::$cache = new ClassNamePhpCache(Config::getInstance()->getCacheDir());
        } else {
            $this->dispatchEvent(new ClassNameInMemoryCacheCreatedEvent());
            self::$cache = new InMemoryCache(ClassNamePhpCache::class);
        }

        return self::$cache;
    }

    /**
     * @return list<string>
     */
    private function getProjectNamespaces(): array
    {
        return $this->setupGacela->getProjectNamespaces();
    }
}
