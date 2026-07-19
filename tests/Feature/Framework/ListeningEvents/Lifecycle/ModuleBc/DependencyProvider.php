<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\Lifecycle\ModuleBc;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class DependencyProvider extends AbstractDependencyProvider
{
    public const GREETING = 'lifecycle-bc-greeting';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::GREETING, static fn (): string => 'hello bc lifecycle');
    }
}
