<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Container\Fixtures;

final class DeepD
{
    public function __construct(
        public readonly DeepC $c,
    ) {
    }
}
