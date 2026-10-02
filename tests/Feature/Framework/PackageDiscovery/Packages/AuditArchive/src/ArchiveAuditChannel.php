<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditArchive;

use Gacela\Framework\Attribute\Plugin;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditChannelInterface;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditRecorder;

#[Plugin(AuditChannelInterface::class)]
final class ArchiveAuditChannel implements AuditChannelInterface
{
    public function write(string $message): void
    {
        AuditRecorder::record('archive: ' . $message);
    }
}
