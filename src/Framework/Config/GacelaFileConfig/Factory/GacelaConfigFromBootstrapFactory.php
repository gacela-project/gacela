<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;

final class GacelaConfigFromBootstrapFactory implements GacelaConfigFileFactoryInterface
{
    /** @var array<string,mixed> */
    private array $globalServices;

    /**
     * @param array<string,mixed> $globalServices
     */
    public function __construct(array $globalServices)
    {
        $this->globalServices = $globalServices;
    }

    public function createGacelaFileConfig(): GacelaConfigFile
    {
        /**
         * @var array{
         *     config?: callable,
         *     mapping-interfaces?: callable,
         *     suffix-types?: callable,
         * } $configFromGlobalServices
         */
        $configFromGlobalServices = $this->globalServices;

        $configBuilder = new ConfigBuilder();
        $configFromGlobalServicesFn = $configFromGlobalServices['config'] ?? null;
        if ($configFromGlobalServicesFn !== null) {
            $configFromGlobalServicesFn($configBuilder);
        }

        $mappingInterfacesBuilder = new MappingInterfacesBuilder();
        $mappingInterfacesFn = $configFromGlobalServices['mapping-interfaces'] ?? null;
        if ($mappingInterfacesFn !== null) {
            $mappingInterfacesFn($mappingInterfacesBuilder, $this->globalServices);
        }

        $suffixTypesBuilder = new SuffixTypesBuilder();
        $suffixTypesFn = $configFromGlobalServices['suffix-types'] ?? null;
        if ($suffixTypesFn !== null) {
            $suffixTypesFn($suffixTypesBuilder);
        }

        return GacelaConfigFile::usingBuilders($configBuilder, $mappingInterfacesBuilder, $suffixTypesBuilder);
    }
}
