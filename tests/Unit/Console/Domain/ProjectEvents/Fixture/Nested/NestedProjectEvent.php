<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture\Nested;

use GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture\ProjectBaseEvent;

/**
 * Two things at once: an event a directory deeper than the scan root, and one
 * that gets the interface from its parent while still being named for it.
 */
final class NestedProjectEvent extends ProjectBaseEvent
{
    public function toString(): string
    {
        return 'nested project event';
    }
}
