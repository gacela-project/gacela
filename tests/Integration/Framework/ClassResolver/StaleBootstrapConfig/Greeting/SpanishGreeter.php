<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\StaleBootstrapConfig\Greeting;

final class SpanishGreeter implements GreeterInterface
{
    public function greet(): string
    {
        return 'hola';
    }
}
