<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting\Domain;

final class Greeter
{
    public function __construct(
        private readonly string $greeting,
    ) {
    }

    public function greet(): string
    {
        return $this->greeting;
    }
}
