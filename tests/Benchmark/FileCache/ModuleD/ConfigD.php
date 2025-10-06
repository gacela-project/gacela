<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleD;

use Gacela\Framework\AbstractConfig;

final class ConfigD extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return $this->get('config-key');
    }
}
