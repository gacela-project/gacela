<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Bootstrap\BuilderConfigurationInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

/**
 * Runs a setup's three builder hooks and assembles the resulting config file.
 *
 * Every builder hook is invoked before any of them is materialized with
 * `build()`, so a hook can still observe the state a previous hook left behind.
 *
 * @psalm-import-type ExternalServicesMap from BuilderConfigurationInterface
 */
final class GacelaConfigFileAssembler
{
    /**
     * @param ExternalServicesMap $externalServices
     */
    public static function assemble(
        BuilderConfigurationInterface $setup,
        array $externalServices = [],
    ): GacelaConfigFileInterface {
        $configBuilder = $setup->buildAppConfig(new AppConfigBuilder());
        $bindingsBuilder = $setup->buildBindings(new BindingsBuilder(), $externalServices);
        $suffixTypesBuilder = $setup->buildSuffixTypes(new SuffixTypesBuilder());

        return (new GacelaConfigFile())
            ->setConfigItems($configBuilder->build())
            ->setBindings($bindingsBuilder->build())
            ->setSuffixTypes($suffixTypesBuilder->build());
    }
}
