<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Dependent\Service;

final class HelloName
{
    public function greet(string $name): array
    {
        return [sprintf('Hello, %s from Dependent.', $name)];
    }
}
