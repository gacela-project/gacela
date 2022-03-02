<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use function class_exists;

final class ClassValidator implements ClassValidatorInterface
{
    public function isClassNameValid(string $className): bool
    {
        return class_exists($className);
    }
}
