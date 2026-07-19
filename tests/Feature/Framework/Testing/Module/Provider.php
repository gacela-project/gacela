<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Testing\Module;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class Provider extends AbstractProvider
{
    public const GREETING = 'testing-greeting';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::GREETING, static fn (): string => 'greeting');
    }
}
