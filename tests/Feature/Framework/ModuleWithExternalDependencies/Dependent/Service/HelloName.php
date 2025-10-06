<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Dependent\Service;

use function sprintf;

final class HelloName
{
    public function greet(string $name): array
    {
        return [sprintf('Hello, %s from Dependent.', $name)];
    }
}
