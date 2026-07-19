<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class CheckoutModuleProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
