<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

use Gacela\Framework\Attribute\Cacheable;

/**
 * A plain class on purpose: the check reflects a pillar for `#[Cacheable]`
 * methods and does not care what the class extends, and one that really
 * extended `AbstractFacade` would be a new module in `tests/` -- module counts
 * are asserted elsewhere.
 */
final class CacheableSubject
{
    #[Cacheable(ttl: 3600)]
    public function cached(): string
    {
        return 'cached';
    }

    public function plain(): string
    {
        return 'plain';
    }
}
