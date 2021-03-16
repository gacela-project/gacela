<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder;

interface ClassNameFinderInterface
{
    public function findClassName(string $moduleName, string $classNamePattern): ?string;
}
