<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendConfig\Module\Infrastructure;

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Fixtures\StringValue;

final class BindingStringValue
{
    public function __invoke(GacelaConfig $config): void
    {
        $config->addBinding(
            StringValue::class,
            new StringValue('Set from BindingStringValue'),
        );
    }
}
