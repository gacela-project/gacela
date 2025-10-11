<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendService\Module;

use ArrayObject;
use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function getArrayAsObject(): ArrayObject
    {
        return $this->getFactory()->getArrayAsObject();
    }

    public function getArrayFromFunction(): ArrayObject
    {
        return $this->getFactory()->getArrayFromFunction();
    }
}
