<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;

interface ClassNameFinderInterface
{
    public function findClassName(ClassInfo $classInfo, string $resolvableType): ?string;
}
