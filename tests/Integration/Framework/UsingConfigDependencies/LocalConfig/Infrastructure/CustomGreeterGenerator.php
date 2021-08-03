<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Infrastructure;

use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain\GreeterGeneratorInterface;

final class CustomGreeterGenerator implements GreeterGeneratorInterface
{
    public function greet(string $name): string
    {
        return "Hello {$name}!";
    }
}
