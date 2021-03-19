<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleGreeting;

use Gacela\AbstractFacade;

/**
 * @method ExampleModuleGreetingFactory getFactory()
 */
final class ExampleModuleGreetingFacade extends AbstractFacade implements ExampleModuleGreetingFacadeInterface
{
    public function greet(string $name): string
    {
        return $this->getFactory()
            ->createGreeter()
            ->greet($name);
    }
}
