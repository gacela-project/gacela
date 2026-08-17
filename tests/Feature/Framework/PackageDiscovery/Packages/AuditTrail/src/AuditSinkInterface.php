<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail;

/**
 * A default this package binds and a consuming application is expected to
 * replace. The replacement is one `addBinding()` in the application's own
 * `gacela.php`, which is merged after every package.
 */
interface AuditSinkInterface
{
    public function label(): string;
}
