<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

final class AppModule
{
    private function __construct(
        private string $moduleName,
        private string  $facadeClass,
        private ?string $factoryClass = null,
        private ?string $configClass = null,
        private ?string $dependencyProviderClass = null,
    ) {
    }

    public static function fromClass(string $facadeClass): self
    {
        $parts = explode('\\', $facadeClass);
        array_pop($parts);
        $moduleName = (string)end($parts);

        return new self(
            $moduleName,
            $facadeClass,
        );
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

    public function factoryClass(): ?string
    {
        return $this->factoryClass;
    }

    public function configClass(): ?string
    {
        return $this->configClass;
    }

    public function dependencyProviderClass(): ?string
    {
        return $this->dependencyProviderClass;
    }
}
