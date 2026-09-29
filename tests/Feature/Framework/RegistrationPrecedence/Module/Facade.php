<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\RegistrationPrecedence\Module;

use ArrayObject;
use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function getService(): ArrayObject
    {
        return $this->getFactory()->getService();
    }
}
