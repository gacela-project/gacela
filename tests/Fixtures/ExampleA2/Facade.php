<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleA2;

use Gacela\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade implements FacadeInterface
{
    public function greet(string $name): array
    {
        return $this->getFactory()
            ->createServiceA()
            ->greet($name);
    }
}
