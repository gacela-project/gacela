<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Health;

use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;

/**
 * What `doctor` asks Billing about itself. A real deployment would reach the
 * ledger here and report what came back; the in-memory one behind this app is
 * always there, so the check exists to show where that answer belongs.
 */
final class LedgerHealthCheck implements ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::healthy('invoice ledger reachable');
    }

    public function getModuleName(): string
    {
        return 'Billing';
    }
}
