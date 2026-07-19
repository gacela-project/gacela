<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\Lifecycle\Module;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class Provider extends AbstractProvider
{
    public const GREETING = 'lifecycle-greeting';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::GREETING, static fn (): string => 'hello lifecycle');
    }
}
