<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\AbstractFactory\SharedApp;

use Gacela\Framework\AbstractFactory;

final class AlphaFactory extends AbstractFactory
{
    public function clock(): ClockInterface
    {
        return $this->make(AlphaClockHolder::class)->clock;
    }
}
