<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\InterfaceSyncFixture;

use Gacela\Framework\AbstractFacade;

/**
 * Both methods delegate, so the only rule with anything to say about this class
 * is the one under test. A fixture that also tripped `facadeOnlyDelegates`
 * would make "the interface is in sync" indistinguishable from "some other rule
 * happened to be quiet".
 *
 * @method SyncFixtureFactory getFactory()
 */
final class SyncFixtureFacade extends AbstractFacade implements SyncFixtureFacadeInterface
{
    /**
     * Silent: the interface declares it, so a consumer type-hinting the
     * interface can reach it.
     */
    public function inSync(): string
    {
        return $this->getFactory()->inSync();
    }

    /**
     * Reported: public on the Facade and absent from the interface, so a
     * consumer type-hinting the interface cannot reach it.
     */
    public function driftedAway(): string
    {
        return $this->getFactory()->driftedAway();
    }
}
