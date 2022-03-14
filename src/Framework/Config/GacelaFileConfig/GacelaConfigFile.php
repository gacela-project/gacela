<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

final class GacelaConfigFile implements GacelaConfigFileInterface
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

    private function __construct()
    {
    }

    public static function withDefaults(): self
    {
        return (new self())
            ->setConfigItems([new GacelaConfigItem()])
            ->setSuffixTypes(SuffixTypesBuilder::DEFAULT_SUFFIX_TYPES);
    }

    public static function usingBuilders(
        ConfigBuilder $configBuilder,
        MappingInterfacesBuilder $mappingInterfacesBuilder,
        SuffixTypesBuilder $suffixTypesBuilder
    ): self {
        return (new self())
            ->setConfigItems($configBuilder->build())
            ->setMappingInterfaces($mappingInterfacesBuilder->build())
            ->setSuffixTypes($suffixTypesBuilder->build());
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
