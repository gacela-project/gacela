<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaConfigBuilder;

final class SuffixTypesBuilder
{
    public const DEFAULT_SUFFIX_TYPES = [
        'Facade' => self::DEFAULT_FACADES,
        'Factory' => self::DEFAULT_FACTORIES,
        'Config' => self::DEFAULT_CONFIGS,
        'Provider' => self::DEFAULT_PROVIDERS,
    ];

    private const DEFAULT_FACADES = ['Facade'];

    private const DEFAULT_FACTORIES = ['Factory'];

    private const DEFAULT_CONFIGS = ['Config'];

    private const DEFAULT_PROVIDERS = ['Provider'];

    /** @var list<string> */
    private array $facades = self::DEFAULT_FACADES;

    /** @var list<string> */
    private array $factories = self::DEFAULT_FACTORIES;

    /** @var list<string> */
    private array $configs = self::DEFAULT_CONFIGS;

    /** @var list<string> */
    private array $providers = self::DEFAULT_PROVIDERS;

    public function addFacade(string $suffix): self
    {
        $this->facades[] = $suffix;

        return $this;
    }

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

    public function addProvider(string $suffix): self
    {
        $this->providers[] = $suffix;

        return $this;
    }

    /**
     * @return array{
     *     Facade: list<string>,
     *     Factory: list<string>,
     *     Config: list<string>,
     *     Provider: list<string>,
     * }
     */
    public function build(): array
    {
        return [
            'Facade' => array_values(array_unique($this->facades)),
            'Factory' => array_values(array_unique($this->factories)),
            'Config' => array_values(array_unique($this->configs)),
            'Provider' => array_values(array_unique($this->providers)),
        ];
    }
}
