<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

final class AppModule
{
    public function __construct(
        private string $moduleName,
        private string $facadeClass,
        private ?string $factoryClass = null,
        private ?string $configClass = null,
        private ?string $dependencyProviderClass = null,
    ) {
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
    public function dependencyProviderClass(): ?string
    {
        return $this->dependencyProviderClass;
    }
}
