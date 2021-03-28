<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder\Rule;

use Gacela\ClassResolver\ClassInfo;
use Generator;

interface FinderRuleInterface
{
    /**
     * @return Generator<string>
     */
    public function buildClassCandidates(ClassInfo $classInfo, string $resolvableType): Generator;
}
