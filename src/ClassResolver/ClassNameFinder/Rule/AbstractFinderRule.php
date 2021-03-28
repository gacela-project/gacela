<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder\Rule;

use Gacela\ClassResolver\ClassInfo;
use Generator;

abstract class AbstractFinderRule implements FinderRuleInterface
{
    public function buildClassCandidates(ClassInfo $classInfo, string $resolvableType): Generator
    {
        foreach ($this->getPatternPaths() as $pattern) {
            yield $this->withPattern($pattern, $classInfo, $resolvableType);
        }
    }

    /**
     * @return list<string>
     */
    abstract protected function getPatternPaths(): array;

    abstract protected function withPattern(string $pattern, ClassInfo $classInfo, string $resolvableType): string;
}
