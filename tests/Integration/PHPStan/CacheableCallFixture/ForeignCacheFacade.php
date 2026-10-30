<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\CacheableCallFixture;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Attribute;
use GacelaTest\Fixtures\ForeignCache\Cacheable;

/**
 * @extends AbstractFacade<CacheableCallFixtureFactory>
 */
final class ForeignCacheFacade extends AbstractFacade
{
    #[Cacheable(60, 'foreign-import')]
    public function imported(int $id): int
    {
        return $id;
    }

    #[\GacelaTest\Fixtures\ForeignCache\Cacheable(60, 'foreign-qualified')]
    public function qualified(int $id): int
    {
        return $id;
    }

    #[Attribute\Cacheable(60, 'gacela-partial')]
    public function gacelaPartial(int $id): int
    {
        return $id;
    }

}
