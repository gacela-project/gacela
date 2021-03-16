<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameCandidatesBuilder;

interface ClassNameBuilderConfigInterface
{
    public function getProjectNamespace(): string;
}
