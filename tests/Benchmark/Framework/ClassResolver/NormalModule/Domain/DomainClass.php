<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\NormalModule\Domain;

final class DomainClass
{
    private array $configValues;
    private string $valueFromDependencyProvider;

    public function __construct(
        array $configValues,
        string $valueFromDependencyProvider
    ) {
        $this->configValues = $configValues;
        $this->valueFromDependencyProvider = $valueFromDependencyProvider;
    }

    public function getConfigValues(): array
    {
        return $this->configValues;
    }

    public function getValueFromDependencyProvider(): string
    {
        return $this->valueFromDependencyProvider;
    }
}
