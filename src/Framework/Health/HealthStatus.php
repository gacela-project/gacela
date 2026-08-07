<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

use function array_map;
use function count;
use function implode;
use function sprintf;

/**
 * Value object representing the health status of a module.
 *
 * @psalm-type HealthStatusArray = array{level: string, message: string, metadata: array<string,mixed>}
 */
final class HealthStatus
{
    /**
     * @param HealthLevel $level The health level
     * @param string $message Human-readable message describing the status
     * @param array<string,mixed> $metadata Additional contextual information
     */
    private function __construct(
        public readonly HealthLevel $level,
        public readonly string $message,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * Create a healthy status.
     *
     * @param array<string,mixed> $metadata
     */
    public static function healthy(string $message = 'Module is healthy', array $metadata = []): self
    {
        return new self(HealthLevel::HEALTHY, $message, $metadata);
    }

    /**
     * Create a degraded status (working but with issues).
     *
     * @param array<string,mixed> $metadata
     */
    public static function degraded(string $message, array $metadata = []): self
    {
        return new self(HealthLevel::DEGRADED, $message, $metadata);
    }

    /**
     * Create an unhealthy status (not working properly).
     *
     * @param array<string,mixed> $metadata
     */
    public static function unhealthy(string $message, array $metadata = []): self
    {
        return new self(HealthLevel::UNHEALTHY, $message, $metadata);
    }

    /**
     * Collapse several checks for one module into its worst status without
     * discarding the individual results that led to it.
     *
     * @param non-empty-list<self> $statuses
     */
    public static function aggregate(array $statuses): self
    {
        $worstLevel = HealthLevel::HEALTHY;

        foreach ($statuses as $status) {
            if ($status->isUnhealthy()) {
                $worstLevel = HealthLevel::UNHEALTHY;
                break;
            }

            if ($status->isDegraded()) {
                $worstLevel = HealthLevel::DEGRADED;
            }
        }

        $summaries = array_map(
            static fn (self $status): string => sprintf('[%s] %s', $status->level->value, $status->message),
            $statuses,
        );

        return new self(
            $worstLevel,
            sprintf('%d health checks: %s', count($statuses), implode('; ', $summaries)),
            ['health_checks' => array_map(static fn (self $status): array => $status->toArray(), $statuses)],
        );
    }

    public function isHealthy(): bool
    {
        return $this->level === HealthLevel::HEALTHY;
    }

    public function isDegraded(): bool
    {
        return $this->level === HealthLevel::DEGRADED;
    }

    public function isUnhealthy(): bool
    {
        return $this->level === HealthLevel::UNHEALTHY;
    }

    /**
     * Convert to array for serialization.
     *
     * @return HealthStatusArray
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level->value,
            'message' => $this->message,
            'metadata' => $this->metadata,
        ];
    }
}
