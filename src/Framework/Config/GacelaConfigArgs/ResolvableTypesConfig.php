<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaConfigArgs;

final class ResolvableTypesConfig
{
    /** @var list<string> */
    private array $factories = ['Factory'];

    /** @var list<string> */
    private array $configs = ['Config'];

    /** @var list<string> */
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
            'Factory' => array_values(array_unique($this->factories)),
            'Config' => array_values(array_unique($this->configs)),
            'DependencyProvider' => array_values(array_unique($this->dependencyProviders)),
        ];
    }
}
