<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidator;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidatorInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithModulePrefix;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithoutModulePrefix;

final class ClassResolverFactory
{
    public function __construct(
        private readonly SetupGacelaInterface $setupGacela,
    ) {
    }

    public function createClassNameFinder(): ClassNameFinderInterface
    {
        return new ClassNameFinder(
            $this->createClassValidator(),
            $this->createFinderRules(),
            $this->getCache(),
            $this->getProjectNamespaces(),
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
        return ClassResolverCache::getCache();
    }

    /**
     * @return list<string>
     */
    private function getProjectNamespaces(): array
    {
        return $this->setupGacela->getProjectNamespaces();
    }
}
