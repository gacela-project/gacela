<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\NormalModule;

use Gacela\Framework\AbstractConfig;

final class NormalModuleConfig extends AbstractConfig
{
    public function getValues(): array
    {
        return ['1', 2, [3]];
    }
}
