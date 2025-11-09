<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;

use function sprintf;

final class FinderRuleWithoutModulePrefix implements FinderRuleInterface
{
    public function buildClassCandidate(string $projectNamespace, string $resolvableType, ClassInfo $classInfo): string
    {
        if ($projectNamespace !== '') {
            return sprintf(
                '\\%s\\%s\\%s',
                trim($projectNamespace, '\\'),
                $classInfo->getModuleName(),
                $resolvableType,
            );
        }

        return sprintf(
            '\\%s\\%s',
            $classInfo->getModuleName(),
            $resolvableType,
        );
    }
}
