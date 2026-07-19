<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ScalarContextualBindings\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function greet(): string
    {
        return $this->getFactory()->greeting();
    }
}
