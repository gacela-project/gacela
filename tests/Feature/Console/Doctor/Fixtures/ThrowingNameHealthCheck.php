<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor\Fixtures;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use RuntimeException;

final class ThrowingNameHealthCheck implements ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::healthy();
    }

    public function getModuleName(): string
    {
        throw new RuntimeException('module name unavailable');
    }
}
