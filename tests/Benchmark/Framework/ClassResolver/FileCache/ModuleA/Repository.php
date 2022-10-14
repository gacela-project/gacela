<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleA;

final class Repository
{
    public function getAll(): array
    {
        return ['a'];
    }
}
