<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile\MissingProviderFile;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function error(): void
    {
        $this->getFactory()->createDomainService();
    }
}
