<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ReadOnlyAppRoot;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function greet(): string
    {
        return $this->getFactory()->createGreetingService()->greet();
    }
}
