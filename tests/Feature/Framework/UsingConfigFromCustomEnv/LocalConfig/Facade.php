<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingConfigFromCustomEnv\LocalConfig;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function doSomething(): array
    {
        return $this->getFactory()->getArrayConfig();
    }
}
