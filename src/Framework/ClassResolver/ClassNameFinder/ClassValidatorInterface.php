<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

interface ClassValidatorInterface
{
    /**
     * Narrows a speculative class candidate to a loadable class name, so the
     * caller can treat it as a `class-string` only after this returns true.
     *
     * @phpstan-assert-if-true class-string $className
     */
    public function isClassNameValid(string $className): bool;
}
