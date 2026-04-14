<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class WidgetModuleProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
