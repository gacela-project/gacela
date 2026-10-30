<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\GlobalServices\Counter;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<CounterFactory>
 */
class CounterFacade extends AbstractFacade
{
    public function total(): string
    {
        return $this->getFactory()->createTotal();
    }
}
