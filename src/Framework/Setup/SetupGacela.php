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
     * @deprecated in favor of setConfigFn()
     *
     * @param callable(ConfigBuilder):void $callable
     */
    public function setConfig(callable $callable): BootstrapSetupGacela
    {
        return $this->setConfigFn($callable);
    }

    /**
     * @deprecated in favor of setMappingInterfacesFn()
     *
     * @param callable(MappingInterfacesBuilder,array<string,mixed>):void $callable
     */
    public function setMappingInterfaces(callable $callable): BootstrapSetupGacela
    {
        return $this->setMappingInterfacesFn($callable);
    }

    /**
     * @deprecated in favor of setSuffixTypesFn()
     *
     * @param callable(SuffixTypesBuilder):void $callable
     */
    public function setSuffixTypes(callable $callable): BootstrapSetupGacela
    {
        return $this->setSuffixTypesFn($callable);
    }
}
