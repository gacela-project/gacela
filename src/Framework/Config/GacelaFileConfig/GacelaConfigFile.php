<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\GacelaConfigArgs\SuffixTypesBuilder;

final class GacelaConfigFile
{
    /** @var list<GacelaConfigItem> */
    private array $configItems = [];

    /** @var array<class-string,class-string|callable|object> */
    private array $mappingInterfaces = [];

    /**
     * @var array{
     *     Factory?:list<string>,
     *     Config?:list<string>,
     *     DependencyProvider?:list<string>,
     * }
     */
    private array $suffixTypes = [];

    public static function withDefaults(): self
    {
        return (new self())
            ->setConfigItems([GacelaConfigItem::withDefaults()])
            ->setSuffixTypes([
                'Factory' => SuffixTypesBuilder::DEFAULT_FACTORIES,
                'Config' => SuffixTypesBuilder::DEFAULT_CONFIGS,
                'DependencyProvider' => SuffixTypesBuilder::DEFAULT_DEPENDENCY_PROVIDERS,
            ]);
    }

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
     * @param array<class-string,class-string|callable|object> $mappingInterfaces
     */
    public function setMappingInterfaces(array $mappingInterfaces): self
    {
        $this->mappingInterfaces = $mappingInterfaces;

        return $this;
    }

    /**
     * Map interfaces to concrete classes or callable (which will be resolved on runtime).
     * This is util to inject dependencies to Gacela services (such as Factories, for example) via their constructor.
     *
     * @return mixed
     */
    public function getMappingInterface(string $key)
    {
        return $this->mappingInterfaces[$key] ?? null;
    }

    /**
     * @param array{
     *     Factory?:list<string>,
     *     Config?:list<string>,
     *     DependencyProvider?:list<string>
     * } $suffixTypes
     */
    public function setSuffixTypes(array $suffixTypes): self
    {
        $this->suffixTypes = $suffixTypes;

        return $this;
    }

    /**
     * @return array{
     *     Factory?:list<string>,
     *     Config?:list<string>,
     *     DependencyProvider?:list<string>
     * }
     */
    public function getSuffixTypes(): array
    {
        return $this->suffixTypes;
    }
}
