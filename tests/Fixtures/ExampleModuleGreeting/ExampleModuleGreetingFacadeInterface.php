<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleGreeting;

interface ExampleModuleGreetingFacadeInterface
{
    public function greet(string $name): string;
}
