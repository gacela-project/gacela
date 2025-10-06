<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;

use function sprintf;

final class FinderRuleWithModulePrefix implements FinderRuleInterface
{
    /**
     * @return class-string
     */
    public function buildClassCandidate(string $projectNamespace, string $resolvableType, ClassInfo $classInfo): string
    {
        if ($projectNamespace !== '') {
            return sprintf(
                '\\%s\\%s\\%s',
                trim($projectNamespace, '\\'),
                $classInfo->getModuleName(),
                $classInfo->getModuleName() . $resolvableType,
            );
        }

        return sprintf(
            '\\%s\\%s',
            $classInfo->getModuleName(),
            $classInfo->getModuleName() . $resolvableType,
        );
    }
}
