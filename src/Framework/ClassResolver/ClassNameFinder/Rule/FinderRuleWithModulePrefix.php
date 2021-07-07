<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;

final class FinderRuleWithModulePrefix implements FinderRuleInterface
{
    public function buildClassCandidate(ClassInfo $classInfo, string $resolvableType): string
    {
        return sprintf(
            '\\%s\\%s%s',
            $classInfo->getFullNamespace(),
            $classInfo->getModule(),
            $resolvableType
        );
    }
}
