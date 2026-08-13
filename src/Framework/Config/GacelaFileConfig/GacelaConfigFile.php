<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

/**
 * @psalm-import-type SuffixTypes from SuffixTypesBuilder
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 */
final class GacelaConfigFile implements GacelaConfigFileInterface
{
    /** @var list<GacelaConfigItem> */
    private array $configItems = [];

    /** @var BindingsMap */
    private array $bindings = [];

    /** @var SuffixTypes */
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
     * @param BindingsMap $bindings
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
     * @return BindingsMap
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * @param SuffixTypes $suffixTypes
     */
    public function setSuffixTypes(array $suffixTypes): self
    {
        $this->suffixTypes = $suffixTypes;

        return $this;
    }

    /**
     * @return SuffixTypes
     */
    public function getSuffixTypes(): array
    {
        return $this->suffixTypes;
    }

    public function merge(GacelaConfigFileInterface $other): GacelaConfigFileInterface
    {
        $new = clone $this;
        $new->configItems = [...$this->configItems, ...$other->getConfigItems()];
        $new->bindings = [...$this->bindings, ...$other->getBindings()];
        // Every kind either side declared, not the four that used to be the
        // only ones: a project-declared kind pushed in through setSuffixTypes()
        // did not survive a merge, which made it unusable in a gacela.php that
        // is merged with another.
        $merged = [];
        foreach ([...array_keys($this->suffixTypes), ...array_keys($other->getSuffixTypes())] as $kind) {
            $merged[$kind] = $this->filterList($other, $kind);
        }

        $new->suffixTypes = $merged;

        return $new;
    }

    /**
     * @return list<string>
     */
    private function filterList(GacelaConfigFileInterface $other, string $key): array
    {
        $merged = array_merge(
            $this->suffixTypes[$key] ?? [],
            $other->getSuffixTypes()[$key] ?? [],
        );
        $filtered = array_filter(array_unique($merged), static fn (string $str): bool => $str !== '');
        /** @var list<non-empty-string> $values */
        $values = array_values($filtered);

        return $values;
    }
}
