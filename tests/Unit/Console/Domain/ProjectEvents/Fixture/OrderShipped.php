<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture;

use Gacela\Framework\Event\GacelaEventInterface;

/**
 * No `Event` suffix: a project grouping its events in an `Event` namespace
 * names them after what happened, and the interface is what says it is one.
 */
final class OrderShipped implements GacelaEventInterface
{
    public function toString(): string
    {
        return 'order shipped';
    }
}
