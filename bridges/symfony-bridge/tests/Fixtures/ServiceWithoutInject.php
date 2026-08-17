<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures;

final class ServiceWithoutInject
{
    public function __construct(
        public readonly FooInterface $foo,
    ) {
    }
}
