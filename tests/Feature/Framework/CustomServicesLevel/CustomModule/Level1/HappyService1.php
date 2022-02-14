<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule\Level1;

use Gacela\Framework\CustomServiceInterface;

final class HappyService1 implements CustomServiceInterface
{
    public function greet(string $name): string
    {
        return "Hi, $name!";
    }
}
