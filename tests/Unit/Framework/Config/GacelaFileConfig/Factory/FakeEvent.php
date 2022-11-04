<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Event\GacelaEventInterface;

final class FakeEvent implements GacelaEventInterface
{
    public function toString(): string
    {
        return 'test event';
    }
}
