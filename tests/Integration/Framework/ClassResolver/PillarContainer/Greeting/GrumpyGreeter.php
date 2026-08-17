<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting;

final class GrumpyGreeter implements GreeterInterface
{
    public function greet(): string
    {
        return 'hello from a definitions file';
    }
}
