<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;

final class ClassNameFinder implements ClassNameFinderInterface
{
    private ClassValidatorInterface $classValidator;

    /** @var list<FinderRuleInterface> */
    private array $finderRules;

    private CacheInterface $classNameCache;

    /** @var list<string> */
    private array $projectNamespaces;

    /**
     * @param list<FinderRuleInterface> $finderRules
     * @param list<string> $projectNamespaces
     */
    public function __construct(
        ClassValidatorInterface $classValidator,
        array $finderRules,
        CacheInterface $classNameCache,
        array $projectNamespaces
    ) {
        $this->classValidator = $classValidator;
        $this->finderRules = $finderRules;
        $this->classNameCache = $classNameCache;
        $this->projectNamespaces = $projectNamespaces;
    }

    /**
     * @psalm-suppress MoreSpecificReturnType,LessSpecificReturnStatement
     *
     * @param list<string> $resolvableTypes
     *
     * @return class-string|null
     */
    public function findClassName(ClassInfo $classInfo, array $resolvableTypes): ?string
    {
        $cacheKey = $classInfo->getCacheKey();

        if ($this->classNameCache->has($cacheKey)) {
            return $this->classNameCache->get($cacheKey);
        }
        $projectNamespaces = $this->projectNamespaces;
        $projectNamespaces[] = $classInfo->getModuleNamespace();

        foreach ($projectNamespaces as $projectNamespace) {
            foreach ($this->finderRules as $finderRule) {
                foreach ($resolvableTypes as $resolvableType) {
                    $className = $finderRule->buildClassCandidate($projectNamespace, $resolvableType, $classInfo);
                    if ($this->classValidator->isClassNameValid($className)) {
                        $this->classNameCache->put($cacheKey, $className);

                        return $className;
                    }
                }
            }
        }

        return null;
    }
}
