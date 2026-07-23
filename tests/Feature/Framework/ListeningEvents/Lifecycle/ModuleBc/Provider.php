<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\Lifecycle\ModuleBc;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class Provider extends AbstractProvider
{
    public const GREETING = 'lifecycle-bc-greeting';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::GREETING, static fn (): string => 'hello bc lifecycle');
    }
}
