<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

final class GacelaConfigFile
{
    /** @var list<GacelaConfigItem> */
    private array $configItems = [];

    /** @var array<class-string,class-string|callable> */
    private array $mappingInterfaces = [];

    /**
     * @var array{
     *     Factory?:list<string>,
     *     Config?:list<string>,
     *     DependencyProvider?:list<string>,
     * }
     */
    private array $overrideResolvableTypes = [];

    public static function withDefaults(): self
    {
        return (new self())
            ->setConfigItems([GacelaConfigItem::withDefaults()])
            ->setOverrideResolvableTypes([
                'Factory' => ['Factory'],
                'Config' => ['Config'],
                'DependencyProvider' => ['DependencyProvider'],
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
     * @param array<class-string,class-string|callable> $mappingInterfaces
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
     * @param array{Factory?:list<string>, Config?:list<string>, DependencyProvider?:list<string>} $overrideResolvableTypes
     */
    public function setOverrideResolvableTypes(array $overrideResolvableTypes): self
    {
        $this->overrideResolvableTypes = $overrideResolvableTypes;

        return $this;
    }

    /**
     * @return array{Factory?:list<string>, Config?:list<string>, DependencyProvider?:list<string>}
     */
    public function getOverrideResolvableTypes(): array
    {
        return $this->overrideResolvableTypes;
    }
}
