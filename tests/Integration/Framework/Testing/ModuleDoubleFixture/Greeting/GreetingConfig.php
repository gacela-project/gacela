<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting;

use Gacela\Framework\AbstractConfig;

/**
 * Not final: see {@see GreetingFactory}.
 */
class GreetingConfig extends AbstractConfig
{
    public function language(): string
    {
        return 'en';
    }
}
