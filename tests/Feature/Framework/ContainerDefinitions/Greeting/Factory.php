<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerDefinitions\Greeting;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createGreeter(): Greeter
    {
        return $this->make(Greeter::class);
    }
}
