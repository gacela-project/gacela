<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

/**
 * Implement this interface in your modules to provide health check capabilities.
 */
interface ModuleHealthCheckInterface
{
    /**
     * Check the health of the module.
     * This can include database connectivity, external API availability, file permissions, etc.
     *
     * @return HealthStatus The current health status of the module
     */
    public function checkHealth(): HealthStatus;

    /**
     * Get the name of the module being checked.
     *
     * @return string The module name (e.g., "User", "Order", "Payment")
     */
    public function getModuleName(): string;
}
