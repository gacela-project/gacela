<?php

declare(strict_types=1);

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<RootFactory>
 */
final class RootFacade extends AbstractFacade
{
    public function hello(): string
    {
        return $this->getFactory()->createHello();
    }
}
