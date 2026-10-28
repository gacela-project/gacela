<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Locator;

final class GreeterA implements Greeter
{
    public function hi(): string
    {
        return 'A';
    }
}
