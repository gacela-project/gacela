<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture\StatefulFixture\Farewell;

use Gacela\Framework\AbstractFacade;

/**
 * A module with no Provider, which is its own path through the Factory.
 *
 * @extends AbstractFacade<FarewellFactory>
 */
final class FarewellFacade extends AbstractFacade
{
    public function bye(string $name): string
    {
        return $this->getFactory()->farewell($name);
    }
}
