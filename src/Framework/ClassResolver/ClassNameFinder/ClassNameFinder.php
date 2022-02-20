<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;

final class ClassNameFinder implements ClassNameFinderInterface
{
    /** @var array<string,array<string,string>> */
    private static array $cachedClassNames = [];

    /** @var list<FinderRuleInterface> */
    private array $finderRules;

    /**
     * @param list<FinderRuleInterface> $finderRules
     */
    public function __construct(array $finderRules)
    {
        $this->finderRules = $finderRules;
    }

    public function findClassName(ClassInfo $classInfo, string $resolvableType): ?string
    {
        if (isset(self::$cachedClassNames[$classInfo->toString()][$resolvableType])) {
            return self::$cachedClassNames[$classInfo->toString()][$resolvableType];
        }

        foreach ($this->finderRules as $finderRule) {
            $className = $finderRule->buildClassCandidate($classInfo, $resolvableType);
            if (class_exists($className)) {
                self::$cachedClassNames[$classInfo->toString()][$resolvableType] = $className;
                return $className;
            }
        }

        return null;
    }
}
