<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleGreeting\Greeter;

interface GreeterInterface
{
    public function greet(string $name): string;
}
