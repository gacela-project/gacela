<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

use Throwable;

use function count;
use function sprintf;

/**
 * Service for running health checks on all registered modules.
 */
final class HealthChecker
{
    /**
     * @param list<ModuleHealthCheckInterface> $healthChecks
     */
    public function __construct(
        private readonly array $healthChecks = [],
    ) {
    }

    /**
     * Run all health checks and return aggregated results.
     *
     * @return HealthCheckReport The aggregated health report
     */
    public function checkAll(): HealthCheckReport
    {
        $results = [];

        foreach ($this->healthChecks as $healthCheck) {
            try {
                $status = $healthCheck->checkHealth();
                $results[$healthCheck->getModuleName()] = $status;
            } catch (Throwable $e) {
                $results[$healthCheck->getModuleName()] = HealthStatus::unhealthy(
                    sprintf('Health check failed: %s', $e->getMessage()),
                    ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()],
                );
            }
        }

        return new HealthCheckReport($results);
    }

    /**
     * Get the total number of registered health checks.
     */
    public function count(): int
    {
        return count($this->healthChecks);
    }
}
