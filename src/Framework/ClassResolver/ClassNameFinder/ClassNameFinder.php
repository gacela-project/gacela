<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;

final class ClassNameFinder implements ClassNameFinderInterface
{
    /** @var array<string,string> */
    private static array $cachedClassNames = [];

    private ClassValidatorInterface $classValidator;

    /** @var list<FinderRuleInterface> */
    private array $finderRules;

    /**
     * @param list<FinderRuleInterface> $finderRules
     */
    public function __construct(
        ClassValidatorInterface $classValidator,
        array $finderRules
    ) {
        $this->classValidator = $classValidator;
        $this->finderRules = $finderRules;
    }

    /**
     * @internal
     */
    public static function resetCachedClassNames(): void
    {
        self::$cachedClassNames = [];
    }

    /**
     * @param list<string> $resolvableTypes
     */
    public function findClassName(ClassInfo $classInfo, array $resolvableTypes): ?string
    {
        $cacheKey = $classInfo->getCacheKey();

        if (isset(self::$cachedClassNames[$cacheKey])) {
            return self::$cachedClassNames[$cacheKey];
        }

        foreach ($this->finderRules as $finderRule) {
            foreach ($resolvableTypes as $resolvableType) {
                $className = $finderRule->buildClassCandidate($classInfo, $resolvableType);
                if ($this->classValidator->isClassNameValid($className)) {
                    self::$cachedClassNames[$cacheKey] = $className;
                    return $className;
                }
            }
        }

        return null;
    }
}
