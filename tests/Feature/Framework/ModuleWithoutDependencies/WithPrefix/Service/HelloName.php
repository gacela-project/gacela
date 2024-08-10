<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithoutDependencies\WithPrefix\Service;

use function sprintf;

final class HelloName
{
    public function greet(string $name): array
    {
        return [sprintf('Hello, %s from WithPrefix.', $name)];
    }
}
