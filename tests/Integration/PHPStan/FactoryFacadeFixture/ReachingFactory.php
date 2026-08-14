<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\FactoryFacadeFixture;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;

/**
 * Reported: a Factory reaching for a Facade. Same-module access goes through
 * the Factory itself and cross-module access goes through the Provider, so this
 * call is either a loop back into the module's own entry point or a dependency
 * the Provider never declared.
 *
 * @method object getFacade()
 *
 * @extends AbstractFactory<AbstractConfig>
 */
final class ReachingFactory extends AbstractFactory
{
    public function reachOut(): object
    {
        return $this->getFacade();
    }
}
