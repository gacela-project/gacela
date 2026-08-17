<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

final class AppModule
{
    /**
     * @param class-string $facadeClass
     * @param ?class-string $factoryClass
     * @param ?class-string $configClass
     * @param ?class-string $providerClass
     * @param array<string, PillarResolutionFailure> $resolutionFailures kind => what was thrown resolving it
     */
    public function __construct(
        private readonly string $fullModuleName,
        private readonly string $moduleName,
        private readonly string $facadeClass,
        private readonly ?string $factoryClass = null,
        private readonly ?string $configClass = null,
        private readonly ?string $providerClass = null,
        private readonly array $resolutionFailures = [],
    ) {
    }

    public function fullModuleName(): string
    {
        return $this->fullModuleName;
    }

    public function moduleName(): string
    {
        return $this->moduleName;
    }

    /**
     * @return class-string
     */
    public function facadeClass(): string
    {
        return $this->facadeClass;
    }

    /**
     * @return ?class-string
     */
    public function factoryClass(): ?string
    {
        return $this->factoryClass;
    }

    /**
     * @return ?class-string
     */
    public function configClass(): ?string
    {
        return $this->configClass;
    }

    /**
     * @return ?class-string
     */
    public function providerClass(): ?string
    {
        return $this->providerClass;
    }

    /**
     * What was thrown while resolving this kind, or null when nothing was: a
     * kind that resolved, and a kind the module simply does not have, both
     * answer null.
     */
    public function resolutionFailure(string $kind): ?PillarResolutionFailure
    {
        return $this->resolutionFailures[$kind] ?? null;
    }
}
