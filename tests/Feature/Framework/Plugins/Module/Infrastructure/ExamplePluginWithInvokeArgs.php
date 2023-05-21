<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Plugins\Module\Infrastructure;

use Gacela\Framework\Container\Container;
use GacelaTest\Fixtures\StringValue;

final class ExamplePluginWithInvokeArgs
{
    public function __invoke(Container $container): void
    {
        $string = $container->getLocator()->get(StringValue::class);

        $string?->setValue('Set from plugin ExamplePluginWithInvokeArgs');
    }
}
