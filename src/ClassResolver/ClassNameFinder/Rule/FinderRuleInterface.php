<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder\Rule;

use Gacela\ClassResolver\ClassInfo;

interface FinderRuleInterface
{
    public function buildClass(ClassInfo $classInfo, string $resolvableType): string;
}
