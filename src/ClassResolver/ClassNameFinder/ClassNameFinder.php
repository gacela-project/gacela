<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder;

use Gacela\ClassResolver\ClassInfo;

final class ClassNameFinder implements ClassNameFinderInterface
{
    public function findClassName(ClassInfo $classInfo, string $resolvableType): ?string
    {
        $className = $this->buildClassName($classInfo, $resolvableType);

        if (class_exists($className)) {
            return $className;
        }

        return null;
    }

    private function buildClassName(ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf('\\%s\\%s%s', $classInfo->getFullNamespace(), $classInfo->getModule(), $resolvableType);
    }
}
