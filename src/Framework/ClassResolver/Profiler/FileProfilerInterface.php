<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Profiler;

interface FileProfilerInterface
{
    public function updateProfiler(array $cache): void;
}
