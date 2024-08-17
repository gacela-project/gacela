<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\NormalModule\Domain;

final class DomainClass
{
    public function __construct(
        private readonly array $configValues,
        private readonly string $valueFromAbstractProvider,
    ) {
    }

    public function getConfigValues(): array
    {
        return $this->configValues;
    }

    public function getValueFromAbstractProvider(): string
    {
        return $this->valueFromAbstractProvider;
    }
}
