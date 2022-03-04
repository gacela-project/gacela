<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

interface ClassValidatorInterface
{
    public function isClassNameValid(string $className): bool;
}
