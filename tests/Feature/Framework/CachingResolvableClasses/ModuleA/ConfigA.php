<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleA;

use Gacela\Framework\AbstractConfig;

final class ConfigA extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return $this->get('config-key');
    }
}
