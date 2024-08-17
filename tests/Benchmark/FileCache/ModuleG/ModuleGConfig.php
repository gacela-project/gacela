<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleG;

use Gacela\Framework\AbstractConfig;

final class ModuleGConfig extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return $this->get('config-key');
    }
}
