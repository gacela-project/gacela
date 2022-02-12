<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;

final class FinderRuleWithModulePrefix implements FinderRuleInterface
{
    public function buildClassCandidate(
        ClassInfo $classInfo,
        string $resolvableType,
        string $flexibleServicePath = ''
    ): string {
        $classname = !empty($flexibleServicePath)
            ? $flexibleServicePath . '\\' . $classInfo->getModule() . $resolvableType
            : $classInfo->getModule() . $resolvableType;

        return sprintf(
            '\\%s\\%s',
            $classInfo->getFullNamespace(),
            $classname
        );
    }
}
