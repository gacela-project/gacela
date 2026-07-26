<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;

interface FinderRuleInterface
{
    /**
     * Builds a speculative class name. The candidate is not guaranteed to name
     * an existing class; validate it before treating it as a `class-string`.
     *
     * @return non-empty-string
     */
    public function buildClassCandidate(string $projectNamespace, string $resolvableType, ClassInfo $classInfo): string;
}
