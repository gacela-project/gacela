<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\Module;

use Gacela\Framework\AbstractFacade;

final class Config extends AbstractFacade
{
    public function getConfigValue(): string
    {
        return 'config-value';
    }
}
