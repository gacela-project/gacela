<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

interface GacelaJsonConfigFactoryInterface
{
    public function createGacelaJsonConfig(): GacelaJsonConfig;
}
