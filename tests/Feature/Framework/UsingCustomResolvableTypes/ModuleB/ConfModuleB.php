<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes\ModuleB;

use Gacela\Framework\AbstractConfig;

final class ConfModuleB extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return $this->get('config-key');
    }
}
