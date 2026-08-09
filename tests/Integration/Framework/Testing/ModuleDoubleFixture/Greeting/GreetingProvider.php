<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

/**
 * Not final: see {@see GreetingFactory}.
 */
class GreetingProvider extends AbstractProvider
{
    public const GREETING = 'GREETING';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::GREETING, 'hello from the provider');
    }
}
