<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Plugins\Module\Infrastructure;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;

use GacelaTest\Fixtures\StringValue;

final class ExampleBeforePluginWithoutConstructor
{
    public function __invoke(GacelaConfig $config): void
    {
        $string = Gacela::get(StringValue::class);

        $string->setValue('Set from plugin ExamplePluginWithoutConstructor');
    }
}
