<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ModuleWithoutDependencies\WithPrefix\Service;

final class HelloName
{
    public function greet(string $name): array
    {
        return ["Hello, $name from WithPrefix."];
    }
}
