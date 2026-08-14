<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\DelegationFixture;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;

/**
 * @extends AbstractFactory<AbstractConfig>
 */
final class DelegationFixtureFactory extends AbstractFactory
{
    public function total(int $a, int $b): int
    {
        return ($a + $b) * 2;
    }
}
