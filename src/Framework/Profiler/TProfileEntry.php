<?php

declare(strict_types=1);

namespace Gacela\Framework\Profiler;

final readonly class TProfileEntry
{
    /**
     * @param non-empty-string $operation
     * @param non-empty-string $subject
     */
    public function __construct(
        public string $operation,
        public string $subject,
        public float $startTime,
        public float $endTime,
        public float $duration,
        public int $memoryUsage,
    ) {
    }
}
