<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC\ExampleD;

use Gacela\AbstractFacade;

/**
 * @method ExampleDFactory getFactory()
 */
final class ExampleDFacade extends AbstractFacade implements ExampleDFacadeInterface
{
    public function greet(string $name): array
    {
        return $this->getFactory()
            ->createServiceA()
            ->greet($name);
    }
}
