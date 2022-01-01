<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

final class GacelaConfigFile
{
    /** @var array<string,GacelaConfigItem> */
    private array $configs = [];

    /** @var array<string,string|callable> */
    private array $mappingInterfaces = [];

    /**
     * @param array<string,GacelaConfigItem> $configs
     */
    public function setConfigs(array $configs): self
    {
        $this->configs = $configs;

        return $this;
    }

    /**
     * @param array<string,string|callable> $mappingInterfaces
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
            ->setConfigs([$configItem->type() => $configItem]);
    }

    /**
     * @return array<string,GacelaConfigItem>
     */
    public function getConfigs(): array
    {
        return $this->configs;
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
