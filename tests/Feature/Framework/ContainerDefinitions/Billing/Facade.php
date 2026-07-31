<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerDefinitions\Billing;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function notifierName(): string
    {
        return $this->getFactory()->createInvoice()->notifierName();
    }
}
