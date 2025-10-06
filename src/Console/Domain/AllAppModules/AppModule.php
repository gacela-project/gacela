<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

final class AppModule
{
    public function __construct(
        private readonly string $fullModuleName,
        private readonly string $moduleName,
        private readonly string $facadeClass,
        private readonly ?string $factoryClass = null,
        private readonly ?string $configClass = null,
        private readonly ?string $providerClass = null,
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
}
