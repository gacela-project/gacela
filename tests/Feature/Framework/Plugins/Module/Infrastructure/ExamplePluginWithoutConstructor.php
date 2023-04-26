<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Plugins\Module\Infrastructure;

use Gacela\Framework\Gacela;
use Gacela\Framework\Plugin\PluginInterface;
use GacelaTest\Fixtures\StringValue;

final class ExamplePluginWithoutConstructor implements PluginInterface
{
    public function run(): void
    {
        $string = Gacela::get(StringValue::class);

        $string->setValue('Set from plugin ExamplePluginWithoutConstructor');
    }
}
