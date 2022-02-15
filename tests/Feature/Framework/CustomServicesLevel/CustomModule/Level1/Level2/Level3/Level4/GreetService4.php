<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule\Level1\Level2\Level3\Level4;

use Gacela\Framework\CustomServiceInterface;

final class GreetService4 implements CustomServiceInterface
{
    public function greet(string $name): string
    {
        return "Greetings, $name! From level 4";
    }
}
