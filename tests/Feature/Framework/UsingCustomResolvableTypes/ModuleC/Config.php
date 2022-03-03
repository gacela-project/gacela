<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes\ModuleC;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return $this->get('config-key');
    }
}
