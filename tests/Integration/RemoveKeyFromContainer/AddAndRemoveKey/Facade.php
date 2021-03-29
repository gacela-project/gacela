<?php

declare(strict_types=1);

namespace GacelaTest\Integration\RemoveKeyFromContainer\AddAndRemoveKey;

use Gacela\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function doSomething(): void
    {
        $this->getFactory()->createDomainService();
    }
}
