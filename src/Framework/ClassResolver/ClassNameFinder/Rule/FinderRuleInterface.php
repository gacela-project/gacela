<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;
use Generator;

interface FinderRuleInterface
{
    /**
     * @return Generator<string>
     */
    public function buildClassCandidates(ClassInfo $classInfo, string $resolvableType): Generator;
}
