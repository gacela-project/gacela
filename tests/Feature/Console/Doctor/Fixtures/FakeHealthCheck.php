<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor\Fixtures;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;

final class FakeHealthCheck implements ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::healthy('FakeHealthCheck ran');
    }

    public function getModuleName(): string
    {
        return 'FakeModule';
    }
}
