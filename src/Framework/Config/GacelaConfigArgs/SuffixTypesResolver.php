<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaConfigArgs;

final class SuffixTypesResolver
{
    public const DEFAULT_FACTORIES = ['Factory'];
    public const DEFAULT_CONFIGS = ['Config'];
    public const DEFAULT_DEPENDENCY_PROVIDERS = ['DependencyProvider'];

    /** @var list<string> */
    private array $factories = self::DEFAULT_FACTORIES;

    /** @var list<string> */
    private array $configs = self::DEFAULT_CONFIGS;

    /** @var list<string> */
    private array $dependencyProviders = self::DEFAULT_DEPENDENCY_PROVIDERS;

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
