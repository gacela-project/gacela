<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

use Gacela\Framework\Attribute\Cacheable;

/**
 * Two annotated methods, so truncating the list to its first entry is
 * something a test can see. With one, every finding is the only finding.
 */
final class TwiceCacheableSubject
{
    #[Cacheable(ttl: 60)]
    public function first(): string
    {
        return 'first';
    }

    #[Cacheable(ttl: 60)]
    public function second(): string
    {
        return 'second';
    }
}
