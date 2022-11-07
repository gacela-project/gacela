<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolver\Module;

use Gacela\Framework\AbstractConfig;

final class FakeConfig extends AbstractConfig
{
    public function getString(): string
    {
        return 'config';
    }
}
