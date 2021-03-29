<?php

declare(strict_types=1);

namespace GacelaTest\Integration\UsingConfig\SimpleModule;

use Gacela\AbstractConfig;

final class Config extends AbstractConfig
{
    public function getNumber(): int
    {
        return (int) $this->get('number');
    }
}
