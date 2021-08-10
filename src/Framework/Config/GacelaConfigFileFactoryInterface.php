<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;

interface GacelaConfigFileFactoryInterface
{
    public function createGacelaFileConfig(): GacelaConfigFile;
}
