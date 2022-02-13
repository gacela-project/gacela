<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices\CustomModule\Application;

final class Greeter
{
    public function greet(string $name): string
    {
        return "Hi, $name!";
    }
}
