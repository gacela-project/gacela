<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm\Fixture;

/**
 * A resolvable class name for the return-type hook to answer with. It only has
 * to exist -- the hook types the key, it does not read the class.
 */
final class ProvidedClock
{
    public function now(): int
    {
        return 0;
    }
}
