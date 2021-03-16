<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameCandidatesBuilder;

interface ClassNameCandidatesBuilderInterface
{
    public function buildClassName(string $module, string $classNamePattern): string;
}
