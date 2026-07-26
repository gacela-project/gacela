<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraphCheck\CycleB;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createName(): string
    {
        return 'b';
    }
}
