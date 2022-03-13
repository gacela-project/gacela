<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaConfigBuilder;

final class SuffixTypesBuilder
{
    public const DEFAULT_SUFFIX_TYPES = [
        'Factory' => self::DEFAULT_FACTORIES,
        'Config' => self::DEFAULT_CONFIGS,
        'DependencyProvider' => self::DEFAULT_DEPENDENCY_PROVIDERS,
    ];

    private const DEFAULT_FACTORIES = ['Factory'];
    private const DEFAULT_CONFIGS = ['Config'];
    private const DEFAULT_DEPENDENCY_PROVIDERS = ['DependencyProvider'];

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
    public function build(): array
    {
        return [
            'Factory' => array_values(array_unique($this->factories)),
            'Config' => array_values(array_unique($this->configs)),
            'DependencyProvider' => array_values(array_unique($this->dependencyProviders)),
        ];
    }
}
