<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use PHPStan\Reflection\ClassReflection;

use function strrpos;
use function substr;

/**
 * @internal shared helpers for Gacela PHPStan rules
 */
trait ClassReflectionHelperTrait
{
    private function extendsClass(ClassReflection $classReflection, string $parent): bool
    {
        foreach ($classReflection->getParents() as $p) {
            if ($p->getName() === $parent) {
                return true;
            }
        }

        return false;
    }

    private function shortClassName(string $className): string
    {
        $pos = strrpos($className, '\\');

        return $pos === false ? $className : substr($className, $pos + 1);
    }
}
