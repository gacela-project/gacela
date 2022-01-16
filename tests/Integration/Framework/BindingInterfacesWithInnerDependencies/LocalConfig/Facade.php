<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
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
