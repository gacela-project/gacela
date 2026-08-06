<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\RebootstrapWithDifferentConfig;

final class English implements GreeterInterface
{
    public function hi(): string
    {
        return 'hello';
    }
}
