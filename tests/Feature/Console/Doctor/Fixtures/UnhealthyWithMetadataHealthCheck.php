<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor\Fixtures;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;

final class UnhealthyWithMetadataHealthCheck implements ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::unhealthy('Broker unreachable', [
            'host' => 'broker.internal',
            'attempts' => 3,
        ]);
    }

    public function getModuleName(): string
    {
        return 'UnhealthyMetaModule';
    }
}
