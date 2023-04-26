<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Plugins\Module\Infrastructure;

use Gacela\Framework\Container\Container;
use Gacela\Framework\Plugin\PluginInterface;
use GacelaTest\Fixtures\StringValue;

final class ExamplePluginWithConstructor implements PluginInterface
{
    public function __construct(
        private Container $container,
    ) {
    }

    public function run(): void
    {
        $string = $this->container->getLocator()->get(StringValue::class);

        $string->setValue('Set from plugin ExamplePluginWithConstructor');
    }
}
