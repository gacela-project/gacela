<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleB;

use Gacela\AbstractFacade;

/**
 * @method ExampleBFactory getFactory()
 */
final class ExampleBFacade extends AbstractFacade implements ExampleBFacadeInterface
{
    public function greet(string $name): array
    {
        return $this->getFactory()
            ->createGreeter()
            ->greet($name);
    }
}
