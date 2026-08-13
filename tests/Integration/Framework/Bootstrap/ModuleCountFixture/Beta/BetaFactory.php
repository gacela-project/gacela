<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ModuleCountFixture\Beta;

use Gacela\Framework\AbstractFactory;

final class BetaFactory extends AbstractFactory
{
    public function buildName(): string
    {
        return 'Beta';
    }
}
