<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\Module;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return 'config-value';
    }
}
