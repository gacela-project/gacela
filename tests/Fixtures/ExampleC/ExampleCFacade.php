<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC;

use Gacela\AbstractFacade;

/**
 * @method ExampleCFactory getFactory()
 */
final class ExampleCFacade extends AbstractFacade
{
    public function greet(string $name): array
    {
        return $this->getFactory()
            ->createGreeter()
            ->greet($name);
    }
}
