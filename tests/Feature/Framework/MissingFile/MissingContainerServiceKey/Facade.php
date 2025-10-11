<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile\MissingContainerServiceKey;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function error(): void
    {
        $this->getFactory()->createDomainService();
    }
}
