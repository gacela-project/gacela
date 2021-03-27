<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC\ExampleD\Service;

final class ServiceD
{
    public function greet(string $name): array
    {
        return ["Hello, $name from A."];
    }
}
