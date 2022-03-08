<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaConfigArgs;

final class ResolvableTypesConfig
{
    private array $factories = ['Factory'];
    private array $configs = ['Config'];
    private array $dependencyProviders = ['DependencyProvider'];

    public function addFactory(string $suffix): self
    {
        $this->factories[] = $suffix;

        return $this;
    }

    public function addConfig(string $suffix): self
    {
        $this->configs[] = $suffix;

        return $this;
    }

    public function addDependencyProvider(string $suffix): self
    {
        $this->dependencyProviders[] = $suffix;

        return $this;
    }

    /**
     * @return array{
     *     Factory:list<string>,
     *     Config:list<string>,
     *     DependencyProvider:list<string>,
     * }
     */
    public function resolve(): array
    {
        return [
            'Factory' => array_unique($this->factories),
            'Config' => array_unique($this->configs),
            'DependencyProvider' => array_unique($this->dependencyProviders),
        ];
    }
}
