<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use function memory_get_usage;
use function microtime;
use function sprintf;

final class PerformanceMetrics
{
    private readonly float $startTime;

    private readonly int $startMemory;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    public function getElapsedTime(): float
    {
        return microtime(true) - $this->startTime;
    }

    public function getMemoryUsed(): int
    {
        return memory_get_usage(true) - $this->startMemory;
    }

    public function formatElapsedTime(): string
    {
        return sprintf('%.3f seconds', $this->getElapsedTime());
    }

    public function formatMemoryUsed(): string
    {
        return BytesFormatter::format($this->getMemoryUsed());
    }
}
