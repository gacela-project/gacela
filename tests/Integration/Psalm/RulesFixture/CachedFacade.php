<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Attribute\Cacheable;
use Gacela\Framework\Attribute\CacheableTrait;

/**
 * A `#[Cacheable]` key that never mentions the arguments, driven through
 * Psalm's own front end -- the analyser has host-free unit tests, and this is
 * the half they cannot cover.
 *
 * @extends AbstractFacade<CleanFactory>
 */
final class CachedFacade extends AbstractFacade
{
    use CacheableTrait;

    #[Cacheable(ttl: 60, key: 'thing')]
    public function bareKey(int $id): string
    {
        return $this->cached(fn (): string => $this->getFactory()->createThing());
    }
}
