<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ResetFromGacelaFile\Library\Pay;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<PayFactory>
 */
final class PayFacade extends AbstractFacade
{
    public function who(): string
    {
        return $this->getFactory()->who();
    }
}
