<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\Module;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createString(): string
    {
        return 'from-factory';
    }
}
