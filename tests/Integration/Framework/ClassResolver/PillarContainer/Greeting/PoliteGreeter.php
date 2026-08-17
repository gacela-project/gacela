<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting;

final class PoliteGreeter implements SecondGreeterInterface
{
    public function greet(): string
    {
        return 'hello from a binding';
    }
}
