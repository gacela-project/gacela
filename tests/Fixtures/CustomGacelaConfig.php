<?php

declare(strict_types=1);

namespace Fixtures;

use Gacela\Framework\Bootstrap\GacelaConfig;

final class CustomGacelaConfig
{
    public function __invoke(GacelaConfig $config): void
    {
        // anything
    }
}
