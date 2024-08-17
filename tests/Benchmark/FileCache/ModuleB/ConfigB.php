<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleB;

use Gacela\Framework\AbstractConfig;

final class ConfigB extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return $this->get('config-key');
    }
}
