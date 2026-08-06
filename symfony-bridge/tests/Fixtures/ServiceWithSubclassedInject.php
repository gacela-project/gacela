<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures;

final class ServiceWithSubclassedInject
{
    public function __construct(
        #[SubclassedInject]
        public readonly FooInterface $foo,
        #[SubclassedInject(ConcreteBar::class)]
        public readonly BarInterface $bar,
    ) {}
}
