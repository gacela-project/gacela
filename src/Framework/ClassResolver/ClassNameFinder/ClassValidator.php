<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Override;

final class ClassValidator implements ClassValidatorInterface
{
    #[Override]
    public function isClassNameValid(string $className): bool
    {
        return class_exists($className);
    }
}
