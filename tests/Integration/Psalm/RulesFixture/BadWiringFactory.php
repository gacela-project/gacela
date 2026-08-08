<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

use Gacela\Framework\AbstractFactory;

/**
 * Both halves of the factory rule, which are different mistakes with different
 * corrections.
 *
 * @extends AbstractFactory<\Gacela\Framework\AbstractConfig>
 */
final class BadWiringFactory extends AbstractFactory
{
    public function createFromFacade(): CleanFacade
    {
        return new CleanFacade();
    }

    public function reachesForTheFacade(): string
    {
        return $this->getFacade()->doThing();
    }
}
