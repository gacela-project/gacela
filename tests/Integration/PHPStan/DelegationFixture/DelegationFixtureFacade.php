<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\DelegationFixture;

use Gacela\Framework\AbstractFacade;

/**
 * Both shapes the rule has to tell apart, in one fixture.
 *
 * @method DelegationFixtureFactory getFactory()
 */
final class DelegationFixtureFacade extends AbstractFacade
{
    /**
     * Reported: the arithmetic is logic no other module can reach and no test
     * can address without going through the Facade.
     */
    public function inlineLogic(int $a, int $b): int
    {
        $sum = $a + $b;

        return $sum * 2;
    }

    /**
     * Silent: this is what the rule asks for.
     */
    public function delegated(int $a, int $b): int
    {
        return $this->getFactory()->total($a, $b);
    }
}
