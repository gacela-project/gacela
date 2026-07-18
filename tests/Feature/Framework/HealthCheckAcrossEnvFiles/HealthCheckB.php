<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\HealthCheckAcrossEnvFiles;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;

final class HealthCheckB implements ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::healthy();
    }

    public function getModuleName(): string
    {
        return 'B';
    }
}
