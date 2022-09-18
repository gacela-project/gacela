<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile\MissingFactoryFile;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;

final class Facade extends AbstractFacade
{
    public function getFactory(): AbstractFactory
    {
        return parent::getFactory();
    }
}
