<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail;

final class FileAuditSink implements AuditSinkInterface
{
    public function label(): string
    {
        return 'file (the package default)';
    }
}
