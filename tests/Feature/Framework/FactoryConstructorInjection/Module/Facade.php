<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FactoryConstructorInjection\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function stamp(): string
    {
        return $this->getFactory()->createStamp();
    }
}
