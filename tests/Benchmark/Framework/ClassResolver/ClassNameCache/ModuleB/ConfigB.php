<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\ClassNameCache\ModuleB;

use Gacela\Framework\AbstractConfig;

final class ConfigB extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return $this->get('config-key');
    }
}