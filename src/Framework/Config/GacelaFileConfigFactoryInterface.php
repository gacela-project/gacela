<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

interface GacelaFileConfigFactoryInterface
{
    public function createGacelaFileConfig(): GacelaConfigFileInterface;
}
