<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Container\Fixtures;

final class DeepC
{
    public function __construct(
        public readonly DeepB $b,
    ) {
    }
}
