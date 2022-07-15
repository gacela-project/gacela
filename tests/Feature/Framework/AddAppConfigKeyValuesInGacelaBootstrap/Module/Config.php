<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\AddAppConfigKeyValuesInGacelaBootstrap\Module;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\ClassResolver\Cache\GacelaCache;

final class Config extends AbstractConfig
{
    public function getData(): array
    {
        return [
            GacelaCache::KEY_ENABLED => $this->get(GacelaCache::KEY_ENABLED),
        ];
    }
}
