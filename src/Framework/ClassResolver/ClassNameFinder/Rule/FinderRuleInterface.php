<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;

interface FinderRuleInterface
{
    public function buildClassCandidate(ClassInfo $classInfo, string $resolvableType): string;
}
