<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture;

use Gacela\Framework\Event\GacelaEventInterface;

/**
 * The ordinary shape: named after the convention and implementing the one
 * interface.
 */
final class SomethingHappenedEvent implements GacelaEventInterface
{
    public function toString(): string
    {
        return 'something happened';
    }
}
