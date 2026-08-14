<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\SuffixFixture;

/**
 * Reported: named like a pillar and is not one, so nothing resolves it and
 * every call site that expects a Facade is wrong about this class.
 */
final class StraySuffixFacade
{
    public function anything(): string
    {
        return 'not a facade at all';
    }
}
