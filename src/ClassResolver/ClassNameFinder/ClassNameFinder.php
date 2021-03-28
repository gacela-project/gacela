<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder;

use Gacela\ClassResolver\ClassInfo;
use Gacela\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;

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
            $classNameCandidates = $finderRule->buildClassCandidates($classInfo, $resolvableType);

            foreach ($classNameCandidates as $className) {
                if (class_exists($className)) {
                    return $className;
                }
            }
        }

        return null;
    }
}
