<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;

interface FinderRuleInterface
{
    public function buildClassCandidate(string $projectNamespace, string $resolvableType, ClassInfo $classInfo): string;
}
