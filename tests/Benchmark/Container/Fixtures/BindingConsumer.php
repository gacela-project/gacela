<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Container\Fixtures;

final class BindingConsumer
{
    public function __construct(
        public readonly ServiceInterface $service,
    ) {
    }
}
