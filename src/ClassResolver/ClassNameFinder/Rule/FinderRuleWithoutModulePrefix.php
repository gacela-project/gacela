<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder\Rule;

use Gacela\ClassResolver\ClassInfo;

final class FinderRuleWithoutModulePrefix extends AbstractFinderRule
{
    protected function getPatternPaths(): array
    {
        return [
            '\\%s\\%s',
        ];
    }

    protected function withPattern(string $pattern, ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf(
            $pattern,
            $classInfo->getFullNamespace(),
            $resolvableType
        );
    }
}
