<?php

declare(strict_types=1);

namespace Gacela\Framework\Setup;

use Gacela\Framework\Bootstrap\SetupGacela as BootstrapSetupGacela;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

/**
 * @deprecated in favor of \Gacela\Framework\Bootstrap\SetupGacela
 */
final class SetupGacela extends BootstrapSetupGacela
{
    /**
     * @deprecated in favor of setConfigBuilder()
     */
    public function setConfig(ConfigBuilder $builder): BootstrapSetupGacela
    {
        return $this->setConfigBuilder($builder);
    }

    /**
     * @deprecated in favor of setMappingInterfacesBuilder()
     */
    public function setMappingInterfaces(MappingInterfacesBuilder $builder): BootstrapSetupGacela
    {
        return $this->setMappingInterfacesBuilder($builder);
    }

    /**
     * @deprecated in favor of setSuffixTypesBuilder()
     */
    public function setSuffixTypes(SuffixTypesBuilder $builder): BootstrapSetupGacela
    {
        return $this->setSuffixTypesBuilder($builder);
    }
}
