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
    private array $externalServices = [];

    private ?ConfigBuilder $configBuilder = null;

    private ?SuffixTypesBuilder $suffixTypesBuilder = null;

    private ?MappingInterfacesBuilder $mappingInterfacesBuilder = null;

    public static function fromGacelaConfig(GacelaConfig $gacelaConfig): self
    {
        return (new self())
            ->setConfigBuilder($gacelaConfig->getConfigBuilder())
            ->setSuffixTypesBuilder($gacelaConfig->getSuffixTypesBuilder())
            ->setMappingInterfacesBuilder($gacelaConfig->getMappingInterfacesBuilder())
            ->setExternalServices($gacelaConfig->getExternalServices());
    }

    public function setMappingInterfacesBuilder(MappingInterfacesBuilder $builder): self
    {
        $this->mappingInterfacesBuilder = $builder;

        return $this;
    }

    public function setSuffixTypesBuilder(SuffixTypesBuilder $builder): self
    {
        $this->suffixTypesBuilder = $builder;

        return $this;
    }

    public function setConfigBuilder(ConfigBuilder $builder): self
    {
        $this->configBuilder = $builder;

        return $this;
    }

    /**
     * @param callable(ConfigBuilder):void $callable
     */
    public function setConfigFn(callable $callable): self
    {
        $this->configFn = $callable;

        return $this;
    }

    public function buildConfig(ConfigBuilder $configBuilder): ConfigBuilder
    {
        if ($this->configBuilder) {
            $configBuilder = $this->configBuilder;
        }

        $this->configFn && ($this->configFn)($configBuilder);

        return $configBuilder;
    }

    /**
     * @param callable(MappingInterfacesBuilder,array<string,mixed>):void $callable
     */
    public function setMappingInterfacesFn(callable $callable): self
    {
        $this->mappingInterfacesFn = $callable;

        return $this;
    }

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string,mixed> $externalServices
     */
    public function buildMappingInterfaces(
        MappingInterfacesBuilder $mappingInterfacesBuilder,
        array $externalServices
    ): MappingInterfacesBuilder {
        if ($this->mappingInterfacesBuilder) {
            $mappingInterfacesBuilder = $this->mappingInterfacesBuilder;
        }

        $this->mappingInterfacesFn && ($this->mappingInterfacesFn)(
            $mappingInterfacesBuilder,
            array_merge($this->externalServices, $externalServices)
        );

        return $mappingInterfacesBuilder;
    }

    /**
     * @param callable(SuffixTypesBuilder):void $callable
     */
    public function setSuffixTypesFn(callable $callable): self
    {
        $this->suffixTypesFn = $callable;

        return $this;
    }

    /**
     * Allow overriding gacela resolvable types.
     */
    public function buildSuffixTypes(SuffixTypesBuilder $suffixTypesBuilder): SuffixTypesBuilder
    {
        if ($this->suffixTypesBuilder) {
            $suffixTypesBuilder = $this->suffixTypesBuilder;
        }

        $this->suffixTypesFn && ($this->suffixTypesFn)($suffixTypesBuilder);

        return $suffixTypesBuilder;
    }

    /**
     * @param array<string,mixed> $array
     */
    public function setExternalServices(array $array): self
    {
        $this->externalServices = $array;

        return $this;
    }

    /**
     * @return array<string,mixed>
     *
     * @deprecated in favor of `externalServices()`
     */
    public function globalServices(): array
    {
        return $this->externalServices();
    }

    /**
     * @return array<string,mixed>
     */
    public function externalServices(): array
    {
        return $this->externalServices;
    }
}
