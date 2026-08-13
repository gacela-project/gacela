<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ModuleCountFixture\Alpha;

use Gacela\Framework\AbstractFactory;

final class AlphaFactory extends AbstractFactory
{
    public function buildName(): string
    {
        return 'Alpha';
    }
}
