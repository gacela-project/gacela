<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ModuleCountFixture\Gamma;

use Gacela\Framework\AbstractFactory;

final class GammaFactory extends AbstractFactory
{
    public function buildName(): string
    {
        return 'Gamma';
    }
}
