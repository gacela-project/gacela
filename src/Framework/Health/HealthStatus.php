<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

/**
 * Value object representing the health status of a module.
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
     * @return array{level: string, message: string, metadata: array<string,mixed>}
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
