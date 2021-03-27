<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder\Rule;

use Gacela\ClassResolver\ClassInfo;

final class FinderRuleWithModulePrefix implements FinderRuleInterface
{
    public function buildClass(ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf(
            '\\%s\\%s%s',
            $classInfo->getFullNamespace(),
            $classInfo->getModule(),
            $resolvableType
        );
    }
}
