<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleA\Infrastructure;

final class Repository
{
    public function getAll(): array
    {
        return ['a'];
    }
}
