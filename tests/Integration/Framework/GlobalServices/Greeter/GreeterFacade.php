<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\GlobalServices\Greeter;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<GreeterFactory>
 */
class GreeterFacade extends AbstractFacade
{
    public function total(): string
    {
        return $this->getFactory()->createTotal();
    }
}
