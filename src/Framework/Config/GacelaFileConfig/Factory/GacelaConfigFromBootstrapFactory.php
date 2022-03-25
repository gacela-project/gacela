<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Setup\SetupGacelaInterface;

final class GacelaConfigFromBootstrapFactory implements GacelaConfigFileFactoryInterface
{
    private SetupGacelaInterface $setup;

    public function __construct(SetupGacelaInterface $setup)
    {
        $this->setup = $setup;
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        $configBuilder = $this->createConfigBuilder();
        $mappingInterfacesBuilder = $this->createMappingInterfacesBuilder();
        $suffixTypesBuilder = $this->createSuffixTypesBuilder();

        return (new GacelaConfigFile())
            ->setConfigItems($configBuilder->build())
            ->setMappingInterfaces($mappingInterfacesBuilder->build())
            ->setSuffixTypes($suffixTypesBuilder->build());
    }

    private function createConfigBuilder(): ConfigBuilder
    {
        $configBuilder = new ConfigBuilder();
        $this->setup->config($configBuilder);

        return $configBuilder;
    }

    private function createMappingInterfacesBuilder(): MappingInterfacesBuilder
    {
        $mappingInterfacesBuilder = new MappingInterfacesBuilder();
        $this->setup->mappingInterfaces($mappingInterfacesBuilder, []);

        return $mappingInterfacesBuilder;
    }

    private function createSuffixTypesBuilder(): SuffixTypesBuilder
    {
        $suffixTypesBuilder = new SuffixTypesBuilder();
        $this->setup->suffixTypes($suffixTypesBuilder);

        return $suffixTypesBuilder;
    }
}
