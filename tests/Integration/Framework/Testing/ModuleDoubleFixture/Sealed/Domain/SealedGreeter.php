<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Sealed\Domain;

final class SealedGreeter
{
    public function __construct(
        private readonly string $greeting = 'hello',
    ) {
    }

    public function greet(): string
    {
        return $this->greeting;
    }
}
