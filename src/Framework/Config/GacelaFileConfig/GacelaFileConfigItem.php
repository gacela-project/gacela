<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

interface GacelaFileConfigItem
{
    public function path(): string;

    public function pathLocal(): string;
}
