<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleC\Infra;

final class Repository
{
    public function getAll(): array
    {
        return ['c'];
    }
}
