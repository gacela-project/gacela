<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile\MissingFactoryFile;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Override;

final class Facade extends AbstractFacade
{
    #[Override]
    public function getFactory(): AbstractFactory
    {
        return parent::getFactory();
    }
}
