<?php

declare(strict_types=1);

namespace GacelaTest\Integration\MissingFile\MissingFactoryFile;

use Gacela\AbstractFacade;

final class Facade extends AbstractFacade
{
    public function error(): void
    {
        $this->getFactory();
    }
}
