<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;

final class FinderRuleWithoutModulePrefix implements FinderRuleInterface
{
    public function buildClassCandidate(string $projectNamespace, string $resolvableType, ClassInfo $classInfo): string
    {
        return sprintf(
            '\\%s\\%s\\%s',
            trim($projectNamespace, '\\'),
            $classInfo->getModuleName(),
            $resolvableType
        );
    }
}
