<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameFinder;

use Gacela\ClassResolver\ClassNameCandidatesBuilder\ClassNameCandidatesBuilderInterface;

final class ClassNameFinder implements ClassNameFinderInterface
{
    protected ClassNameCandidatesBuilderInterface $classNameCandidatesBuilder;

    public function __construct(ClassNameCandidatesBuilderInterface $classNameCandidatesBuilder)
    {
        $this->classNameCandidatesBuilder = $classNameCandidatesBuilder;
    }

    public function findClassName(string $moduleName, string $classNamePattern): ?string
    {
        $classNameCandidate = $this->classNameCandidatesBuilder->buildClassName($moduleName, $classNamePattern);

        return $this->tryClassName($classNameCandidate);
    }

    private function tryClassName(string $classNameCandidate): ?string
    {
        if (class_exists($classNameCandidate)) {
            return $classNameCandidate;
        }

        return null;
    }
}
