<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;

final class ClassNameFinder implements ClassNameFinderInterface
{
    /** @var FinderRuleInterface[] */
    private array $finderRules;

    public function __construct(FinderRuleInterface ...$finderRules)
    {
        $this->finderRules = $finderRules;
    }

    public function findClassName(ClassInfo $classInfo, string $resolvableType): ?string
    {
        foreach ($this->finderRules as $finderRule) {
            $className = $finderRule->buildClassCandidate($classInfo, $resolvableType);

            if (class_exists($className)) {
                return $className;
            }
        }

        return null;
    }
}
