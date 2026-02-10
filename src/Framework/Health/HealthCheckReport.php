<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

/**
 * Aggregated report of all module health checks.
 */
final class HealthCheckReport
{
    /**
     * @param array<string,HealthStatus> $results
     */
    public function __construct(
        private readonly array $results,
    ) {
    }

    /**
     * Check if all modules are healthy.
     */
    public function isHealthy(): bool
    {
        foreach ($this->results as $status) {
            if (!$status->isHealthy()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if any module is unhealthy.
     */
    public function hasUnhealthyModules(): bool
    {
        foreach ($this->results as $status) {
            if ($status->isUnhealthy()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all health check results.
     *
     * @return array<string,HealthStatus>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get results filtered by health level.
     *
     * @return array<string,HealthStatus>
     */
    public function getResultsByLevel(HealthLevel $level): array
    {
        return array_filter(
            $this->results,
            static fn (HealthStatus $status): bool => $status->level === $level,
        );
    }

    /**
     * Get the overall health level (worst case).
     */
    public function getOverallLevel(): HealthLevel
    {
        if ($this->hasUnhealthyModules()) {
            return HealthLevel::UNHEALTHY;
        }

        foreach ($this->results as $status) {
            if ($status->isDegraded()) {
                return HealthLevel::DEGRADED;
            }
        }

        return HealthLevel::HEALTHY;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{overall: string, modules: array<string,array{level: string, message: string, metadata: array<string,mixed>}>}
     */
    public function toArray(): array
    {
        $modules = [];
        foreach ($this->results as $moduleName => $status) {
            $modules[$moduleName] = $status->toArray();
        }

        return [
            'overall' => $this->getOverallLevel()->value,
            'modules' => $modules,
        ];
    }
}
