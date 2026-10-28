<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Locator;

final class GreeterB implements Greeter
{
    public function hi(): string
    {
        return 'B';
    }
}
