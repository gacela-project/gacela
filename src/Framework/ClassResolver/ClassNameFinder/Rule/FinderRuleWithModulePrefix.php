<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;

final class FinderRuleWithModulePrefix extends AbstractFinderRule
{
    protected function getPatternPaths(): array
    {
        return [
            '\\%s\\%s%s',
        ];
    }

    protected function withPattern(string $pattern, ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf(
            $pattern,
            $classInfo->getFullNamespace(),
            $classInfo->getModule(),
            $resolvableType
        );
    }
}
