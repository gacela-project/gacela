<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail;

/**
 * The extension point this package publishes.
 *
 * The package declares the stack and ships one member; the consuming application
 * adds its own by naming this interface, which is the only thing it has to know
 * about the package.
 */
interface AuditChannelInterface
{
    public function write(string $message): void;
}
