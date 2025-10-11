<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function generateCompanyAndName(): string
    {
        return $this->getFactory()
            ->createGreeterService()
            ->generateCompanyAndName();
    }
}
