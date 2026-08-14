<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\FactoryFacadeFixture;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;

/**
 * Silent: reaches its own Config, which is what a Factory is allowed to do.
 *
 * @extends AbstractFactory<AbstractConfig>
 */
final class WellBehavedFactory extends AbstractFactory
{
    public function stayHome(): AbstractConfig
    {
        return $this->getConfig();
    }
}
