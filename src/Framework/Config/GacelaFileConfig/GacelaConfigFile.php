<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

final class GacelaConfigFile
{
    /** @var array<string,GacelaConfigItem> */
    private array $configItems = [];

    /** @var array<class-string,class-string|callable> */
    private array $mappingInterfaces = [];

    /**
     * @param array<string,GacelaConfigItem> $configItems
     */
    public function setConfigItems(array $configItems): self
    {
        $this->configItems = $configItems;

        return $this;
    }

    /**
     * @param array<class-string,class-string|callable> $mappingInterfaces
     */
    public function setMappingInterfaces(array $mappingInterfaces): self
    {
        $this->mappingInterfaces = $mappingInterfaces;

        return $this;
    }

    public static function withDefaults(): self
    {
        $configItem = GacelaConfigItem::withDefaults();

        return (new self())
            ->setConfigItems([$configItem->type() => $configItem]);
    }

    /**
     * @return array<string,GacelaConfigItem>
     */
    public function getConfigItems(): array
    {
        return $this->configItems;
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
}
