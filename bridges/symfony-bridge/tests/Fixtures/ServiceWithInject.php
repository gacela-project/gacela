<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

final class ServiceWithInject
{
    public function __construct(
        #[Inject] public readonly FooInterface $foo,
        #[Inject(ConcreteBar::class)] public readonly BarInterface $bar,
    ) {
    }
}
