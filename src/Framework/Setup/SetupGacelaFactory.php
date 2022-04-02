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
     *     external-services?: array<string,mixed>,
     * } $externalServices
     */
    public static function fromArray(array $externalServices): SetupGacelaInterface
    {
        $setup = new SetupGacela();
        if (isset($externalServices[Gacela::CONFIG])) {
            $setup->setConfig($externalServices[Gacela::CONFIG]);
        }
        if (isset($externalServices[Gacela::MAPPING_INTERFACES])) {
            $setup->setMappingInterfaces($externalServices[Gacela::MAPPING_INTERFACES]);
        }
        if (isset($externalServices[Gacela::SUFFIX_TYPES])) {
            $setup->setSuffixTypes($externalServices[Gacela::SUFFIX_TYPES]);
        }
        if (isset($externalServices[Gacela::EXTERNAL_SERVICES])) {
            $setup->setExternalServices($externalServices[Gacela::EXTERNAL_SERVICES]);
        }

        return $setup;
    }
}
