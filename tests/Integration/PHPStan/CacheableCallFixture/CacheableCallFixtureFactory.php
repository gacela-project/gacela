<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\CacheableCallFixture;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;

/**
 * @extends AbstractFactory<AbstractConfig>
 */
final class CacheableCallFixtureFactory extends AbstractFactory
{
    public function compute(int $id): int
    {
        return $id * 2;
    }
}
