<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;

final class ClassNameFinder implements ClassNameFinderInterface
{
    /**
     * @param list<FinderRuleInterface> $finderRules
     * @param list<string> $projectNamespaces
     */
    public function __construct(
        private readonly ClassValidatorInterface $classValidator,
        private readonly array $finderRules,
        private readonly CacheInterface $cache,
        private readonly array $projectNamespaces,
    ) {
    }

    /**
     * @param list<string> $resolvableTypes
     *
     * @return class-string|null
     */
    public function findClassName(ClassInfo $classInfo, array $resolvableTypes): ?string
    {
        $cacheKey = $classInfo->getCacheKey();

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $projectNamespaces = $this->projectNamespaces;
        $projectNamespaces[] = $classInfo->getModuleNamespace();

        foreach ($projectNamespaces as $projectNamespace) {
            foreach ($this->finderRules as $finderRule) {
                foreach ($resolvableTypes as $resolvableType) {
                    $className = $finderRule->buildClassCandidate($projectNamespace, $resolvableType, $classInfo);

                    if ($this->classValidator->isClassNameValid($className)) {
                        $this->cache->put($cacheKey, $className);

                        return $className;
                    }
                }
            }
        }

        return null;
    }
}
