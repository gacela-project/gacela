<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Container\Fixtures;

final class DeepB
{
    public function __construct(
        public readonly DeepA $a,
    ) {
    }
}
