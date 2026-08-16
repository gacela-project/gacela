<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\StaleBootstrapConfig\Greeting;

/**
 * Resolved through a `#[ServiceMap]` accessor, and built by the container from
 * whichever bootstrap's bindings are in force.
 */
final class GreetingService
{
    public function __construct(
        private readonly GreeterInterface $greeter,
    ) {
    }

    public function greet(): string
    {
        return $this->greeter->greet();
    }
}
