<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures\WarmModule;

use Gacela\Framework\AbstractFactory;

final class WarmFactory extends AbstractFactory
{
    public function createName(): string
    {
        return 'warm';
    }
}
