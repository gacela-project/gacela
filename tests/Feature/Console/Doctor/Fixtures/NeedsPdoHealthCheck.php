<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor\Fixtures;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use PDO;

final class NeedsPdoHealthCheck implements ModuleHealthCheckInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function checkHealth(): HealthStatus
    {
        $this->connection->query('SELECT 1');

        return HealthStatus::healthy();
    }

    public function getModuleName(): string
    {
        return 'Database';
    }
}
