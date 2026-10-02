<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugEventsAttributeListener\Fixture;

use Gacela\Framework\Event\GacelaEventInterface;

final class OrderShippedEvent implements GacelaEventInterface
{
    public function toString(): string
    {
        return 'Order shipped';
    }
}
