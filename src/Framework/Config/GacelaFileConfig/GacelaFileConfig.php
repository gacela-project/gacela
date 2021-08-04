<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

interface GacelaFileConfig
{
    /**
     * @return array<string,GacelaFileConfigItem>
     */
    public function configs(): array;
}
