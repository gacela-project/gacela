<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\App;

use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditSinkInterface;

/**
 * What the application binds instead of the package's default.
 */
final class ProjectAuditSink implements AuditSinkInterface
{
    public function label(): string
    {
        return 'the project decided';
    }
}
