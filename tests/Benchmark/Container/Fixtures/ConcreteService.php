<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Container\Fixtures;

final class ConcreteService implements ServiceInterface
{
    public function value(): string
    {
        return 'concrete';
    }
}
