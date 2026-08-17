<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function greet(): string
    {
        return $this->getFactory()->greeter()->greet();
    }
}
