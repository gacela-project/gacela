<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleA2\Service;

final class ServiceA
{
    public function greet(string $name): array
    {
        return ["Hello, $name from A."];
    }
}
