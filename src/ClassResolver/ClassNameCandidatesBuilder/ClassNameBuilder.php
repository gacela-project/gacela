<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\ClassNameCandidatesBuilder;

final class ClassNameBuilder implements ClassNameCandidatesBuilderInterface
{
    private ClassNameBuilderConfigInterface $config;

    public function __construct(ClassNameBuilderConfigInterface $config)
    {
        $this->config = $config;
    }

    public function buildClassName(string $module, string $classNamePattern): string
    {
        $organization = $this->config->getProjectNamespace();

        return sprintf($classNamePattern, $organization, $module, $module);
    }
}
