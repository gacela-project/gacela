<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

final class GacelaConfigFile implements GacelaConfigFileInterface
{
    /** @var list<GacelaConfigItem> */
    private array $configItems = [];

    /** @var array<class-string,class-string|callable|object> */
    private array $bindings = [];

    /**
     * @var array{
     *     Facade: list<string>,
     *     Factory: list<string>,
     *     Config: list<string>,
     *     Provider: list<string>,
     * }
     */
    private array $suffixTypes = SuffixTypesBuilder::DEFAULT_SUFFIX_TYPES;

    /**
     * @param list<GacelaConfigItem> $configItems
     */
    public function setConfigItems(array $configItems): self
    {
        $this->configItems = $configItems;

        return $this;
    }

    /**
     * @return list<GacelaConfigItem>
     */
    public function getConfigItems(): array
    {
        return $this->configItems;
    }

    /**
     * @param array<class-string,class-string|callable|object> $bindings
     */
    public function setBindings(array $bindings): self
    {
        $this->bindings = $bindings;

        return $this;
    }

    /**
     * Map interfaces to concrete classes or callable (which will be resolved on runtime).
     * This is util to inject dependencies to Gacela services (such as Factories, for example) via their constructor.
     *
     * @return array<class-string,class-string|callable|object>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * @param array{
     *     Facade: list<string>,
     *     Factory: list<string>,
     *     Config: list<string>,
     *     Provider: list<string>,
     * } $suffixTypes
     */
    public function setSuffixTypes(array $suffixTypes): self
    {
        $this->suffixTypes = $suffixTypes;

        return $this;
    }

    /**
     * @psalm-suppress ImplementedReturnTypeMismatch
     *
     * @return array{
     *     Facade: list<string>,
     *     Factory: list<string>,
     *     Config: list<string>,
     *     Provider: list<string>,
     * }
     */
    public function getSuffixTypes(): array
    {
        return $this->suffixTypes;
    }

    public function combine(GacelaConfigFileInterface $other): GacelaConfigFileInterface
    {
        $new = clone $this;
        $new->configItems = [...$this->configItems, ...$other->getConfigItems()];
        /** @psalm-suppress DuplicateArrayKey */
        $new->bindings = [...$this->bindings, ...$other->getBindings()];
        $new->suffixTypes = [
            'Facade' => $this->filterList($other, 'Facade'),
            'Factory' => $this->filterList($other, 'Factory'),
            'Config' => $this->filterList($other, 'Config'),
            'Provider' => $this->filterList($other, 'Provider'),
        ];

        return $new;
    }

    /**
     * @return list<string>
     */
    private function filterList(GacelaConfigFileInterface $other, string $key): array
    {
        $merged = array_merge($this->suffixTypes[$key], $other->getSuffixTypes()[$key]); // @phpstan-ignore-line
        $filtered = array_filter(array_unique($merged), static fn (string $str): bool => $str !== '');
        /** @var list<non-empty-string> $values */
        $values = array_values($filtered);

        return $values;
    }
}
