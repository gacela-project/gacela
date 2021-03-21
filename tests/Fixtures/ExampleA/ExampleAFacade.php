<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleA;

use Gacela\AbstractFacade;

/**
 * @method ExampleAFactory getFactory()
 */
final class ExampleAFacade extends AbstractFacade
{
    public function greet(string $name): array
    {
        return $this->getFactory()
            ->createServiceA()
            ->greet($name);
    }
}
