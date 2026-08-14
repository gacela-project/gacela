<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\CacheableFixture;

use Gacela\Framework\AbstractFactory;

final class CachedFactory extends AbstractFactory
{
    public function createThing(): string
    {
        return 'thing';
    }
}
