<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<CleanFactory>
 */
final class CleanFacade extends AbstractFacade
{
    public function doThing(): string
    {
        return $this->getFactory()->createThing();
    }
}
