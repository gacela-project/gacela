<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<CleanFactory>
 */
final class DriftedFacade extends AbstractFacade implements DriftedFacadeInterface
{
    public function declared(): string
    {
        return $this->getFactory()->createThing();
    }

    public function forgotten(): string
    {
        return $this->getFactory()->createThing();
    }
}
