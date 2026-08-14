<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\CacheableFixture;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Attribute\Cacheable;
use Gacela\Framework\Attribute\CacheableTrait;

/**
 * A `#[Cacheable]` key that never mentions the arguments, driven through
 * PHPStan's own front end.
 *
 * @extends AbstractFacade<CachedFactory>
 */
final class CachedFacade extends AbstractFacade
{
    use CacheableTrait;

    #[Cacheable(ttl: 60, key: 'thing')]
    public function bareKey(int $id): string
    {
        return $this->cached(fn (): string => $this->getFactory()->createThing());
    }

    #[Cacheable(ttl: 60, key: 'thing-{0}')]
    public function keyedByArgument(int $id): string
    {
        return $this->cached(fn (): string => $this->getFactory()->createThing());
    }

    #[Cacheable(ttl: 60, key: 'no-args')]
    public function takesNothing(): string
    {
        return $this->cached(fn (): string => $this->getFactory()->createThing());
    }
}
