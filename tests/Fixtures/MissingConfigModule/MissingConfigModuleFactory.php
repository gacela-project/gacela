<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\MissingConfigModule;

use Gacela\AbstractFactory;

final class MissingConfigModuleFactory extends AbstractFactory
{
    public function createGreeter(): void
    {
        $this->getConfig();
    }
}
