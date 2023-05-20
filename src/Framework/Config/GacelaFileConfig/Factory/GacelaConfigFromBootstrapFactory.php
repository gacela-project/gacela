<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

final class GacelaConfigFromBootstrapFactory implements GacelaConfigFileFactoryInterface
{
    public function __construct(
        private SetupGacelaInterface $bootstrapSetup,
    ) {
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        $configBuilder = $this->createConfigBuilder();
        $bindingsBuilder = $this->createBindingsBuilder();
        $suffixTypesBuilder = $this->createSuffixTypesBuilder();

        return (new GacelaConfigFile())
            ->setConfigItems($configBuilder->build())
            ->setBindings($bindingsBuilder->build())
            ->setSuffixTypes($suffixTypesBuilder->build());
    }

    private function createConfigBuilder(): AppConfigBuilder
    {
        return $this->bootstrapSetup->buildAppConfig(new AppConfigBuilder());
    }

    private function createBindingsBuilder(): BindingsBuilder
    {
        return $this->bootstrapSetup->buildBindings(new BindingsBuilder(), []);
    }

    private function createSuffixTypesBuilder(): SuffixTypesBuilder
    {
        return $this->bootstrapSetup->buildSuffixTypes(new SuffixTypesBuilder());
    }
}
