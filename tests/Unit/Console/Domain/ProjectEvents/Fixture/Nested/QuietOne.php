<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture\Nested;

use GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture\ProjectBaseEvent;

/**
 * An event that says so nowhere: no `Event` in the name, and the interface
 * comes from the parent rather than from this file.
 *
 * The documented limit of the finder, pinned as a test rather than left as a
 * sentence: it is not found, and the class named on the `extends` line is --
 * which is the family target a listener would register against anyway.
 */
final class QuietOne extends ProjectBaseEvent
{
    public function toString(): string
    {
        return 'a quiet one';
    }
}
