<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ModuleExample;

use Gacela\Framework\AbstractConfig;

final class ModuleExampleConfig extends AbstractConfig
{
    public function getValues(): array
    {
        return ['1', 2, [3]];
    }
}
