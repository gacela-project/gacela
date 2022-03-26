<?php

declare(strict_types=1);

namespace Gacela\Framework\Setup;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

final class SetupGacela extends AbstractSetupGacela
{
    /** @var ?callable(ConfigBuilder):void */
    private $configFn = null;

    /** @var ?callable(MappingInterfacesBuilder,array<string,mixed>):void */
    private $mappingInterfacesFn = null;

    /** @var ?callable(SuffixTypesBuilder):void */
    private $suffixTypesFn = null;

    /** @var array<string,mixed> */
    private array $globalServices = [];

    /**
     * @param callable(ConfigBuilder):void $callable
     */
    public function setConfig(callable $callable): self
    {
        $this->configFn = $callable;

        return $this;
    }

    public function config(ConfigBuilder $configBuilder): void
    {
        $this->configFn && ($this->configFn)($configBuilder);
    }

    /**
     * @param callable(MappingInterfacesBuilder,array<string,mixed>):void $callable
     */
    public function setMappingInterfaces(callable $callable): self
    {
        $this->mappingInterfacesFn = $callable;

        return $this;
    }

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string,mixed> $globalServices
     */
    public function mappingInterfaces(MappingInterfacesBuilder $mappingInterfacesBuilder, array $globalServices): void
    {
        $this->mappingInterfacesFn && ($this->mappingInterfacesFn)(
            $mappingInterfacesBuilder,
            array_merge($this->globalServices, $globalServices)
        );
    }

    /**
     * @param callable(SuffixTypesBuilder):void $callable
     */
    public function setSuffixTypes(callable $callable): self
    {
        $this->suffixTypesFn = $callable;

        return $this;
    }

    /**
     * Allow overriding gacela resolvable types.
     */
    public function suffixTypes(SuffixTypesBuilder $suffixTypesBuilder): void
    {
        $this->suffixTypesFn && ($this->suffixTypesFn)($suffixTypesBuilder);
    }

    /**
     * @param array<string,mixed> $array
     */
    public function setGlobalServices(array $array): self
    {
        $this->globalServices = $array;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function globalServices(): array
    {
        return $this->globalServices;
    }
}
