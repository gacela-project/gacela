<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;

final class FinderRuleWithoutModulePrefix implements FinderRuleInterface
{
    public function buildClassCandidate(
        ClassInfo $classInfo,
        string $resolvableType,
        string $customServicePath = ''
    ): string {
        $classname = !empty($customServicePath)
            ? $customServicePath . '\\' . $resolvableType
            : $resolvableType;

        return sprintf(
            '\\%s\\%s',
            $classInfo->getFullNamespace(),
            $classname
        );
    }
}
