<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\Cache\GacelaCache;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidator;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidatorInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithModulePrefix;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithoutModulePrefix;
use Gacela\Framework\Config\Config;

final class ClassResolverFactory
{
    public function createClassNameFinder(): ClassNameFinderInterface
    {
        return new ClassNameFinder(
            $this->createClassValidator(),
            $this->createFinderRules(),
            $this->createClassNameCache()
        );
    }

    public function createClassNameCache(): ClassNameCacheInterface
    {
        if (!$this->isProjectCacheEnabled()) {
            return new InMemoryCache(ClassNameCache::class);
        }

        return new ClassNameCache(
            Config::getInstance()->getCacheDir(),
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

    private function isProjectCacheEnabled(): bool
    {
        return (bool)Config::getInstance()
            ->get(GacelaCache::KEY_ENABLED, GacelaCache::DEFAULT_ENABLED_VALUE);
    }
}
