<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder;

use Gacela\ClassResolver\ClassInfo;

final class ClassNameFinder implements ClassNameFinderInterface
{
    public function findClassName(ClassInfo $classInfo, string $resolvableType): ?string
    {
        $className = $this->buildClassNameWithPrefix($classInfo, $resolvableType);

        if (class_exists($className)) {
            return $className;
        }

        $className = $this->buildClassNameWithoutPrefix($classInfo, $resolvableType);

        if (class_exists($className)) {
            return $className;
        }

        return null;
    }

    private function buildClassNameWithPrefix(ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf('\\%s\\%s%s', $classInfo->getFullNamespace(), $classInfo->getModule(), $resolvableType);
    }

    private function buildClassNameWithoutPrefix(ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf('\\%s\\%s', $classInfo->getFullNamespace(), $resolvableType);
    }
}
