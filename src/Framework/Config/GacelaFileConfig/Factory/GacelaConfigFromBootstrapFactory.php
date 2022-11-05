<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

final class GacelaConfigFromBootstrapFactory implements GacelaConfigFileFactoryInterface
{
    private SetupGacelaInterface $bootstrapSetup;

    public function __construct(SetupGacelaInterface $bootstrapSetup)
    {
        $this->bootstrapSetup = $bootstrapSetup;
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
        return $this->bootstrapSetup->buildConfig(new ConfigBuilder());
    }

    private function createMappingInterfacesBuilder(): MappingInterfacesBuilder
    {
        return $this->bootstrapSetup->buildMappingInterfaces(new MappingInterfacesBuilder(), []);
    }

    private function createSuffixTypesBuilder(): SuffixTypesBuilder
    {
        return $this->bootstrapSetup->buildSuffixTypes(new SuffixTypesBuilder());
    }
}
