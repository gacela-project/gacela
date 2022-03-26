<?php

declare(strict_types=1);

namespace Gacela\Framework\Setup;

use Gacela\Framework\Gacela;

final class SetupGacelaFactory
{
    /**
     * @param array{
     *     config?: callable(\Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder):void,
     *     mapping-interfaces?: callable(\Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder, array<string,mixed>):void,
     *     suffix-types?: callable(\Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder):void,
     *     global-services?: array<string,mixed>,
     * } $globalServices
     */
    public static function fromArray(array $globalServices): SetupGacelaInterface
    {
        $setup = new SetupGacela();
        if (isset($globalServices[Gacela::CONFIG])) {
            $setup->setConfig($globalServices[Gacela::CONFIG]);
        }
        if (isset($globalServices[Gacela::MAPPING_INTERFACES])) {
            $setup->setMappingInterfaces($globalServices[Gacela::MAPPING_INTERFACES]);
        }
        if (isset($globalServices[Gacela::SUFFIX_TYPES])) {
            $setup->setSuffixTypes($globalServices[Gacela::SUFFIX_TYPES]);
        }
        if (isset($globalServices[Gacela::GLOBAL_SERVICES])) {
            $setup->setGlobalServices($globalServices[Gacela::GLOBAL_SERVICES]);
        }

        return $setup;
    }
}
