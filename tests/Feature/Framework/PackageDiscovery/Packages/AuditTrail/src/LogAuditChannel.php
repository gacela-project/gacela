<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail;

use function sprintf;

/**
 * The member this package puts on its own stack.
 */
final class LogAuditChannel implements AuditChannelInterface
{
    public function write(string $message): void
    {
        AuditRecorder::record(sprintf('log: %s', $message));
    }
}
