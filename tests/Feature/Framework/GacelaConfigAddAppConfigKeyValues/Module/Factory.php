<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\GacelaConfigAddAppConfigKeyValues\Module;

use Gacela\Framework\AbstractFactory;

/**
 * @method Config getConfig
 */
final class Factory extends AbstractFactory
{
    public function getConfigData(): array
    {
        return $this->getConfig()
            ->getData();
    }
}
