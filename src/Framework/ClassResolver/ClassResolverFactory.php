<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\GacelaCache;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\Cache\ProfiledCache;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidator;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidatorInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithModulePrefix;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithoutModulePrefix;
use Gacela\Framework\ClassResolver\Profiler\ClassNameJsonProfiler;
use Gacela\Framework\ClassResolver\Profiler\FileProfilerInterface;
use Gacela\Framework\ClassResolver\Profiler\GacelaProfiler;
use Gacela\Framework\Config\Config;

final class ClassResolverFactory
{
    private GacelaCache $gacelaCache;
    private GacelaProfiler $gacelaProfiler;
    private SetupGacelaInterface $setupGacela;

    public function __construct(
        GacelaCache $gacelaCache,
        GacelaProfiler $gacelaProfiler,
        SetupGacelaInterface $setupGacela
    ) {
        $this->gacelaCache = $gacelaCache;
        $this->gacelaProfiler = $gacelaProfiler;
        $this->setupGacela = $setupGacela;
    }

    public function createClassNameFinder(): ClassNameFinderInterface
    {
        return new ClassNameFinder(
            $this->createClassValidator(),
            $this->createFinderRules(),
            $this->createClassNameCache(),
            $this->getProjectNamespaces()
        );
    }

    public function createClassNameCache(): CacheInterface
    {
        $cache = $this->createCache();

        if ($this->gacelaProfiler->isEnabled()) {
            return new ProfiledCache(
                $cache,
                $this->createProfiler()
            );
        }

        return $cache;
    }

    private function createCache(): CacheInterface
    {
        if ($this->gacelaCache->isEnabled()) {
            return new ClassNamePhpCache(
                Config::getInstance()->getCacheDir(),
            );
        }

        return new InMemoryCache(ClassNamePhpCache::class);
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

    /**
     * @return list<string>
     */
    private function getProjectNamespaces(): array
    {
        return $this->setupGacela->getProjectNamespaces();
    }

    private function createProfiler(): FileProfilerInterface
    {
        return new ClassNameJsonProfiler(
            Config::getInstance()->getProfilerDir(),
        );
    }
}
