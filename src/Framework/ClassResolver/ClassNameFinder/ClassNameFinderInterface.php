<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;

interface ClassNameFinderInterface
{
    /**
     * @param list<string> $resolvableTypes
     */
    public function findClassName(ClassInfo $classInfo, array $resolvableTypes): ?string;
}
