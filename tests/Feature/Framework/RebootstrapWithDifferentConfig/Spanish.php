<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\RebootstrapWithDifferentConfig;

final class Spanish implements GreeterInterface
{
    public function hi(): string
    {
        return 'hola';
    }
}
