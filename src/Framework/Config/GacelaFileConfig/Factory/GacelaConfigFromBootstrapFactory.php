<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Bootstrap\BuilderConfigurationInterface;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

final class GacelaConfigFromBootstrapFactory implements GacelaConfigFileFactoryInterface
{
    public function __construct(
        private readonly BuilderConfigurationInterface $bootstrapSetup,
    ) {
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        return GacelaConfigFileAssembler::assemble($this->bootstrapSetup);
    }
}
