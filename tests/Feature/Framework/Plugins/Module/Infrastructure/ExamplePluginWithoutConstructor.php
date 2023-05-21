<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Plugins\Module\Infrastructure;

use Gacela\Framework\Gacela;

use GacelaTest\Fixtures\StringValue;

final class ExamplePluginWithoutConstructor
{
    public function __invoke(): void
    {
        $string = Gacela::get(StringValue::class);

        $string?->setValue('Set from plugin ExamplePluginWithoutConstructor');
    }
}
