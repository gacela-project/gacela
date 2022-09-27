<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileProfiler\ModuleE;

use Gacela\Framework\AbstractConfig;

final class ConfigE extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return $this->get('config-key');
    }
}