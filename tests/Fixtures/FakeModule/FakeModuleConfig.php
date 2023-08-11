<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\FakeModule;

use Gacela\Framework\AbstractConfig;

final class FakeModuleConfig extends AbstractConfig
{
    public function getKey(): string
    {
        return 'key from config';
    }
}
