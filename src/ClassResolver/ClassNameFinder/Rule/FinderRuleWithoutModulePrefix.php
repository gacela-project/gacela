<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder\Rule;

use Gacela\ClassResolver\ClassInfo;

final class FinderRuleWithoutModulePrefix implements FinderRuleInterface
{
    public function buildClass(ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf(
            '\\%s\\%s',
            $classInfo->getFullNamespace(),
            $resolvableType
        );
    }
}
