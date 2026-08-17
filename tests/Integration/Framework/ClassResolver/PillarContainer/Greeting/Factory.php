<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting;

use Gacela\Framework\AbstractFactory;

/**
 * A pillar whose constructor asks for an interface. Which registration verb
 * declared that interface is exactly what this module exists to test.
 */
final class Factory extends AbstractFactory
{
    public function __construct(
        private readonly GreeterInterface $greeter,
    ) {
    }

    public function greeter(): GreeterInterface
    {
        return $this->greeter;
    }
}
