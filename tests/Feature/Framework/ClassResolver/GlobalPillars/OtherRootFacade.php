<?php

declare(strict_types=1);

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<OtherRootFactory>
 */
final class OtherRootFacade extends AbstractFacade
{
    public function hello(): string
    {
        return $this->getFactory()->createHello();
    }
}
