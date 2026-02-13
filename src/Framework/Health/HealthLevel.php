<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

/**
 * Enum representing the health level of a module.
 */
enum HealthLevel: string
{
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case UNHEALTHY = 'unhealthy';
}
