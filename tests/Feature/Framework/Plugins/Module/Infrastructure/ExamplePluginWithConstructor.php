<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Plugins\Module\Infrastructure;

use Gacela\Framework\Container\Container;

use GacelaTest\Fixtures\StringValue;

final class ExamplePluginWithConstructor
{
    public function __construct(
        private Container $container,
    ) {
    }

    public function __invoke(): void
    {
        $string = $this->container->getLocator()->get(StringValue::class);

        $string?->setValue('Set from plugin ExamplePluginWithConstructor');
    }
}
