<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\Extender;

use Gacela\Framework\Bootstrap\GacelaConfig;

final class ExtenderConfig
{
    public function __invoke(GacelaConfig $config): void
    {
        $config->addAppConfigKeyValue('router.prefix', '/api');
    }
}
