<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\MissingFile\MissingFactoryFile;

use Gacela\Framework\AbstractFacade;

final class Facade extends AbstractFacade
{
    public function error(): void
    {
        $this->getFactory();
    }
}
