<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder;

use Gacela\ClassResolver\ClassInfo;

interface ClassNameFinderInterface
{
    public function findClassName(ClassInfo $classInfo, string $resolvableType): ?string;
}
