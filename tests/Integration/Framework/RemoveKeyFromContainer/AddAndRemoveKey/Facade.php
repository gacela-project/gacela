<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RemoveKeyFromContainer\AddAndRemoveKey;

use Gacela\Framework\AbstractFacade;

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
