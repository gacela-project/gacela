<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CacheWarm\TestModule;

use Gacela\Framework\AbstractFacade;

final class TestFacade extends AbstractFacade
{
    public function doSomething(): string
    {
        return 'test';
    }
}
