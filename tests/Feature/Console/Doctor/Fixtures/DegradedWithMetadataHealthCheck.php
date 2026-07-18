<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor\Fixtures;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;

final class DegradedWithMetadataHealthCheck implements ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::degraded('Cache is stale', [
            'stale-entries' => 42,
            'oldest-entry' => '2020-01-01',
            'raw-payload' => ['not' => 'scalar'],
        ]);
    }

    public function getModuleName(): string
    {
        return 'DegradedModule';
    }
}
