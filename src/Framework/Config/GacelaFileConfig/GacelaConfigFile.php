<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\ConfigReaderInterface;

final class GacelaConfigFile
{
    /** @var list<GacelaConfigItem> */
    private array $configItems = [];

    /** @var array<class-string,class-string|callable> */
    private array $mappingInterfaces = [];

    /** @var array<string,ConfigReaderInterface> */
    private array $configReaders = [];

    /** @var list<string> */
    private array $customServicePaths = [];

    public static function withDefaults(): self
    {
        return (new self())
            ->setConfigItems([GacelaConfigItem::withDefaults()])
            ->setConfigReaders(['php' => new PhpConfigReader()]);
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
     * @param array<string,ConfigReaderInterface> $configReaders
     */
    public function setConfigReaders(array $configReaders): self
    {
        $this->configReaders = $configReaders;

        return $this;
    }

    /**
     * @return array<string,ConfigReaderInterface>
     */
    public function getConfigReaders(): array
    {
        return $this->configReaders;
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
     * @param list<string> $customServicePaths
     */
    public function setCustomServicePaths(array $customServicePaths): self
    {
        $this->customServicePaths = $customServicePaths;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCustomServicePaths(): array
    {
        return $this->customServicePaths;
    }
}
