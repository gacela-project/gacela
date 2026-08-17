<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture;

/**
 * Named like an event, implementing nothing. The name is what makes it a
 * candidate and the interface is what refuses it -- which is the whole reason
 * the finder loads the class instead of trusting the filename.
 */
final class NotReallyAnEvent
{
    public function toString(): string
    {
        return 'not an event at all';
    }
}
