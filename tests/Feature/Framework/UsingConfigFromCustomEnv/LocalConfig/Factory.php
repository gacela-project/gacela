<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingConfigFromCustomEnv\LocalConfig;

use Gacela\Framework\AbstractFactory;

/**
 * @extends AbstractFactory<Config>
 */
final class Factory extends AbstractFactory
{
    public function getArrayConfig(): array
    {
        return $this->getConfig()->getArrayConfig();
    }
}
