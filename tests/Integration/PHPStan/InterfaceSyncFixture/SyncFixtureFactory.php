<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\InterfaceSyncFixture;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;

/**
 * @extends AbstractFactory<AbstractConfig>
 */
final class SyncFixtureFactory extends AbstractFactory
{
    public function inSync(): string
    {
        return 'in-sync';
    }

    public function driftedAway(): string
    {
        return 'drifted-away';
    }
}
