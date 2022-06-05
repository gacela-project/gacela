<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\ClassNameCache\ModuleC;

use Gacela\Framework\AbstractConfig;

final class ConfigC extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return $this->get('config-key');
    }
}
