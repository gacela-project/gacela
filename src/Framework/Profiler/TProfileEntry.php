<?php

declare(strict_types=1);

namespace Gacela\Framework\Profiler;

final class TProfileEntry
{
    /**
     * @param non-empty-string $operation
     * @param non-empty-string $subject
     */
    public function __construct(
        public readonly string $operation,
        public readonly string $subject,
        public readonly float $startTime,
        public readonly float $endTime,
        public readonly float $duration,
        public readonly int $memoryUsage,
    ) {
    }
}
