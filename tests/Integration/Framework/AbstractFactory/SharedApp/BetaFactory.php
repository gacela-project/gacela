<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\AbstractFactory\SharedApp;

use Gacela\Framework\AbstractFactory;

final class BetaFactory extends AbstractFactory
{
    public function clock(): ClockInterface
    {
        return $this->make(BetaClockHolder::class)->clock;
    }
}
