<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameCacheInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;

final class ClassNameFinder implements ClassNameFinderInterface
{
    private ClassValidatorInterface $classValidator;

    /** @var list<FinderRuleInterface> */
    private array $finderRules;

    private ClassNameCacheInterface $classNameCache;

    /**
     * @param list<FinderRuleInterface> $finderRules
     */
    public function __construct(
        ClassValidatorInterface $classValidator,
        array $finderRules,
        ClassNameCacheInterface $classNameCache
    ) {
        $this->classValidator = $classValidator;
        $this->finderRules = $finderRules;
        $this->classNameCache = $classNameCache;
    }

    /**
     * @param list<string> $resolvableTypes
     */
    public function findClassName(ClassInfo $classInfo, array $resolvableTypes): ?string
    {
        $cacheKey = $classInfo->getCacheKey();

        if ($this->classNameCache->has($cacheKey)) {
            return $this->classNameCache->get($cacheKey);
        }

        foreach ($this->finderRules as $finderRule) {
            foreach ($resolvableTypes as $resolvableType) {
                $className = $finderRule->buildClassCandidate($classInfo, $resolvableType);
                if ($this->classValidator->isClassNameValid($className)) {
                    $this->classNameCache->put($cacheKey, $className);

                    return $className;
                }
            }
        }

        return null;
    }
}
