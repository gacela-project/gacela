<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\GacelaConfigAddAppConfigKeyValues\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function getConfigData(): array
    {
        return $this->getFactory()
            ->getConfigData();
    }
}
