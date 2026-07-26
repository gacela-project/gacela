<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FactoryConstructorInjection\Module;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function createStamp(): string
    {
        return 'stamp@' . $this->clock->now();
    }
}
