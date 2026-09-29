<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

use Gacela\Framework\Bootstrap\GacelaConfig;

final class ExtenderConfig
{
    public function __invoke(GacelaConfig $config): void
    {
        $config->addAppConfigKeyValue('router.prefix', '/api');
    }
}
