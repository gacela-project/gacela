<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

final class GacelaConfig
{
    private ConfigBuilder $configBuilder;

    private SuffixTypesBuilder $suffixTypesBuilder;

    private MappingInterfacesBuilder $mappingInterfacesBuilder;

    /** @var array<string,class-string|object|callable> */
    private array $externalServices;

    private bool $resetCache = false;

    /**
     * @param array<string,class-string|object|callable> $externalServices
     */
    public function __construct(array $externalServices = [])
    {
        $this->externalServices = $externalServices;
        $this->configBuilder = new ConfigBuilder();
        $this->suffixTypesBuilder = new SuffixTypesBuilder();
        $this->mappingInterfacesBuilder = new MappingInterfacesBuilder();
    }

    /**
     * @return callable(GacelaConfig):void
     */
    public static function withPhpConfigDefault(): callable
    {
        return static function (self $config): void {
            $config->addAppConfig('config/*.php', 'config/local.php');
        };
    }

    /**
     * @param string $path define the path where Gacela will read all the config files
     * @param string $pathLocal define the path where Gacela will read the local config file
     * @param class-string<ConfigReaderInterface>|ConfigReaderInterface|null $reader Define the reader class which will read and parse the config files
     */
    public function addAppConfig(string $path, string $pathLocal = '', $reader = null): self
    {
        $this->configBuilder->add($path, $pathLocal, $reader);

        return $this;
    }

    public function addSuffixTypeFacade(string $suffix): self
    {
        $this->suffixTypesBuilder->addFacade($suffix);

        return $this;
    }

    public function addSuffixTypeFactory(string $suffix): self
    {
        $this->suffixTypesBuilder->addFactory($suffix);

        return $this;
    }

    public function addSuffixTypeConfig(string $suffix): self
    {
        $this->suffixTypesBuilder->addConfig($suffix);

        return $this;
    }

    public function addSuffixTypeDependencyProvider(string $suffix): self
    {
        $this->suffixTypesBuilder->addDependencyProvider($suffix);

        return $this;
    }

    /**
     * @param class-string $key
     * @param class-string|object|callable $value
     */
    public function addMappingInterface(string $key, $value): self
    {
        $this->mappingInterfacesBuilder->bind($key, $value);

        return $this;
    }

    /**
     * @return class-string|object|callable
     */
    public function getExternalService(string $key)
    {
        return $this->externalServices[$key];
    }

    /**
     * @param class-string|object|callable $value
     */
    public function addExternalService(string $key, $value): self
    {
        $this->externalServices[$key] = $value;

        return $this;
    }

    public function setResetCache(bool $flag): self
    {
        $this->resetCache = $flag;

        return $this;
    }

    /**
     * @return array{
     *     external-services:array<string,class-string|object|callable>,
     *     config-builder:ConfigBuilder,
     *     suffix-types-builder:SuffixTypesBuilder,
     *     mapping-interfaces-builder:MappingInterfacesBuilder,
     *     reset-cache:bool,
     * }
     *
     * @internal
     */
    public function build(): array
    {
        return [
            'external-services' => $this->externalServices,
            'config-builder' => $this->configBuilder,
            'suffix-types-builder' => $this->suffixTypesBuilder,
            'mapping-interfaces-builder' => $this->mappingInterfacesBuilder,
            'reset-cache' => $this->resetCache,
        ];
    }
}
