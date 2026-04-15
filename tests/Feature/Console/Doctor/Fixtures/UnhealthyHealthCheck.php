<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor\Fixtures;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;

final class UnhealthyHealthCheck implements ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::unhealthy('Service is down');
    }

    public function getModuleName(): string
    {
        return 'UnhealthyModule';
    }
}
