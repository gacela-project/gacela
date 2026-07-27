<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture\StatefulFixture\Greeting;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Architecture\StatefulFixture\Greeting\Service\Greeter;

final class GreetingFactory extends AbstractFactory
{
    public function createGreeter(): Greeter
    {
        return new Greeter(
            $this->getProvidedDependency(GreetingProvider::PREFIX),
        );
    }
}
